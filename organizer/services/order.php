<?php
/**
 * Service Order Details Page
 * Shows detailed information about a specific service order
 * URL: /organizator/services/{order_id}
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
$pageTitle = 'Detalii Comanda - Servicii';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
require_once dirname(__DIR__, 2) . '/includes/head.php';
require_once dirname(__DIR__, 2) . '/includes/organizer-sidebar.php';

// Get order ID from URL
$orderId = $_GET['id'] ?? '';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__, 2) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Loading State -->
            <div id="loading-state" class="animate-pulse">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-surface rounded"></div>
                    <div class="h-8 bg-surface rounded w-64"></div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl border border-border p-6">
                            <div class="h-6 bg-surface rounded w-48 mb-4"></div>
                            <div class="h-4 bg-surface rounded w-full mb-2"></div>
                            <div class="h-4 bg-surface rounded w-3/4"></div>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="bg-white rounded-2xl border border-border p-6">
                            <div class="h-6 bg-surface rounded w-32 mb-4"></div>
                            <div class="h-10 bg-surface rounded w-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Content (hidden until loaded) -->
            <div id="order-content" class="hidden">
                <!-- Page Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <a href="/organizator/services/orders" class="text-muted hover:text-secondary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </a>
                            <h1 class="text-2xl font-bold text-secondary">Comanda <span id="order-number">#---</span></h1>
                        </div>
                        <p class="text-sm text-muted" id="order-date">---</p>
                    </div>
                    <div class="flex gap-3">
                        <button id="btn-cancel" onclick="cancelOrder()" class="btn btn-secondary hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Anuleaza
                        </button>
                        <a id="btn-invoice" href="#" target="_blank" class="btn btn-secondary hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Descarca Factura
                        </a>
                        <button id="btn-pay" onclick="payOrder()" class="btn btn-primary hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Plateste Acum
                        </button>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Status Banner -->
                        <div id="status-banner" class="rounded-2xl p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div id="status-icon" class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl"></div>
                                    <div>
                                        <p class="text-lg font-bold text-secondary" id="service-type-label">---</p>
                                        <p class="text-sm" id="status-label">---</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-bold text-secondary" id="order-total">0 RON</p>
                                    <p class="text-sm text-muted">Total</p>
                                </div>
                            </div>
                        </div>

                        <!-- Event Info -->
                        <div id="event-card" class="bg-white rounded-2xl border border-border overflow-hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Eveniment</h3>
                            </div>
                            <div class="p-6">
                                <div class="flex gap-4">
                                    <img id="event-image" src="/assets/images/default-event.png" alt="" class="w-24 h-24 rounded-xl object-cover">
                                    <div class="flex-1">
                                        <h4 id="event-title" class="text-lg font-bold text-secondary mb-1">---</h4>
                                        <p id="event-date" class="text-sm text-muted mb-1">---</p>
                                        <p id="event-venue" class="text-sm text-muted">---</p>
                                        <a id="event-link" href="#" class="text-sm text-primary hover:underline mt-2 inline-block">Vezi Evenimentul</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Service Configuration -->
                        <div class="bg-white rounded-2xl border border-border overflow-hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Configuratie Serviciu</h3>
                            </div>
                            <div class="p-6" id="config-content">
                                <!-- Configuration content loaded dynamically -->
                            </div>
                        </div>

                        <!-- Email Campaign Statistics (shown only for email orders) -->
                        <div id="email-stats-card" class="bg-white rounded-2xl border border-border overflow-hidden hidden">
                            <div class="p-4 border-b border-border bg-surface flex items-center justify-between">
                                <h3 class="font-semibold text-secondary">Statistici Campanie Email</h3>
                                <button onclick="refreshEmailStats()" class="text-sm text-primary hover:underline flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Actualizeaza
                                </button>
                            </div>
                            <div class="p-6">
                                <!-- Stats waiting for send -->
                                <div id="stats-pending" class="text-center py-8 hidden">
                                    <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <p class="text-secondary font-medium mb-1">Campania nu a fost inca trimisa</p>
                                    <p class="text-sm text-muted">Statisticile vor fi disponibile dupa trimiterea emailurilor</p>
                                </div>

                                <!-- Stats available -->
                                <div id="stats-content" class="hidden">
                                    <!-- Main Stats -->
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                        <div class="bg-green-50 rounded-xl p-4 text-center">
                                            <p class="text-3xl font-bold text-green-600" id="stat-delivered">0</p>
                                            <p class="text-sm text-green-700">Livrate</p>
                                        </div>
                                        <div class="bg-blue-50 rounded-xl p-4 text-center">
                                            <p class="text-3xl font-bold text-blue-600" id="stat-opens">0</p>
                                            <p class="text-sm text-blue-700">Deschideri</p>
                                        </div>
                                        <div class="bg-purple-50 rounded-xl p-4 text-center">
                                            <p class="text-3xl font-bold text-purple-600" id="stat-clicks">0</p>
                                            <p class="text-sm text-purple-700">Click-uri</p>
                                        </div>
                                        <div class="bg-amber-50 rounded-xl p-4 text-center">
                                            <p class="text-3xl font-bold text-amber-600" id="stat-unsubscribes">0</p>
                                            <p class="text-sm text-amber-700">Dezabonari</p>
                                        </div>
                                    </div>

                                    <!-- Rate Stats -->
                                    <div class="grid grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm text-muted">Open Rate</span>
                                                <span class="text-lg font-bold text-secondary" id="stat-open-rate">0%</span>
                                            </div>
                                            <div class="h-3 bg-surface rounded-full overflow-hidden">
                                                <div id="bar-open-rate" class="h-full bg-blue-500 rounded-full transition-all" style="width: 0%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm text-muted">Click Rate</span>
                                                <span class="text-lg font-bold text-secondary" id="stat-click-rate">0%</span>
                                            </div>
                                            <div class="h-3 bg-surface rounded-full overflow-hidden">
                                                <div id="bar-click-rate" class="h-full bg-purple-500 rounded-full transition-all" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detailed Stats -->
                                    <div class="border-t border-border pt-4">
                                        <p class="text-sm font-medium text-secondary mb-3">Detalii suplimentare</p>
                                        <div class="grid grid-cols-3 gap-4 text-center">
                                            <div class="p-3 bg-surface rounded-lg">
                                                <p class="text-xl font-bold text-secondary" id="stat-bounced">0</p>
                                                <p class="text-xs text-muted">Bounce</p>
                                            </div>
                                            <div class="p-3 bg-surface rounded-lg">
                                                <p class="text-xl font-bold text-secondary" id="stat-complaints">0</p>
                                                <p class="text-xs text-muted">Reclamatii Spam</p>
                                            </div>
                                            <div class="p-3 bg-surface rounded-lg">
                                                <p class="text-xl font-bold text-secondary" id="stat-sent-at">---</p>
                                                <p class="text-xs text-muted">Trimis la</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Featuring Stats (shown only for featuring orders) -->
                        <div id="featuring-stats-card" class="bg-white rounded-2xl border border-border overflow-hidden hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Statistici Promovare</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="bg-primary/10 rounded-xl p-4 text-center">
                                        <p class="text-3xl font-bold text-primary" id="feat-impressions">0</p>
                                        <p class="text-sm text-primary/80">Afisari</p>
                                    </div>
                                    <div class="bg-accent/10 rounded-xl p-4 text-center">
                                        <p class="text-3xl font-bold text-accent" id="feat-clicks">0</p>
                                        <p class="text-sm text-accent/80">Click-uri</p>
                                    </div>
                                    <div class="bg-success/10 rounded-xl p-4 text-center">
                                        <p class="text-3xl font-bold text-success" id="feat-ctr">0%</p>
                                        <p class="text-sm text-success/80">CTR</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Sidebar -->
                    <div class="space-y-6">
                        <!-- Payment Details -->
                        <div class="bg-white rounded-2xl border border-border overflow-hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Detalii Plata</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-muted">Metoda</span>
                                    <span class="font-medium text-secondary" id="payment-method">---</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted">Status</span>
                                    <span id="payment-status" class="px-2 py-1 rounded-full text-sm font-medium">---</span>
                                </div>
                                <div id="payment-date-row" class="flex justify-between hidden">
                                    <span class="text-muted">Platit la</span>
                                    <span class="font-medium text-secondary" id="payment-date">---</span>
                                </div>
                                <div id="payment-ref-row" class="flex justify-between hidden">
                                    <span class="text-muted">Referinta</span>
                                    <span class="font-medium text-secondary text-sm" id="payment-ref">---</span>
                                </div>
                                <div class="border-t border-border pt-4">
                                    <div class="flex justify-between">
                                        <span class="text-muted">Subtotal</span>
                                        <span class="text-secondary" id="payment-subtotal">0 RON</span>
                                    </div>
                                    <div class="flex justify-between mt-2">
                                        <span class="text-muted">TVA (19%)</span>
                                        <span class="text-secondary" id="payment-tax">0 RON</span>
                                    </div>
                                    <div class="flex justify-between mt-2 pt-2 border-t border-border">
                                        <span class="font-semibold text-secondary">Total</span>
                                        <span class="font-bold text-primary text-lg" id="payment-total">0 RON</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div class="bg-white rounded-2xl border border-border overflow-hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Istoric Activitate</h3>
                            </div>
                            <div class="p-6">
                                <div id="timeline" class="space-y-4">
                                    <!-- Timeline items loaded dynamically -->
                                </div>
                            </div>
                        </div>

                        <!-- Actions for Email Orders -->
                        <div id="email-actions-card" class="bg-white rounded-2xl border border-border overflow-hidden hidden">
                            <div class="p-4 border-b border-border bg-surface">
                                <h3 class="font-semibold text-secondary">Actiuni</h3>
                            </div>
                            <div class="p-6 space-y-3">
                                <a id="btn-send-email" href="#" class="btn btn-accent w-full hidden">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Trimite Campania
                                </a>
                                <button id="btn-preview-email" onclick="previewEmail()" class="btn btn-secondary w-full">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Previzualizeaza Email
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <div id="error-state" class="hidden">
                <div class="bg-white rounded-2xl border border-border p-12 text-center">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-secondary mb-2">Comanda nu a fost gasita</h2>
                    <p class="text-muted mb-6">Comanda pe care o cauti nu exista sau nu ai acces la ea.</p>
                    <a href="/organizator/services/orders" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Inapoi la Comenzi
                    </a>
                </div>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

let order = null;
const orderId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();

const serviceTypes = {
    featuring: { label: 'Promovare Eveniment', color: 'primary', icon: '⭐', bg: 'bg-primary/10' },
    email: { label: 'Email Marketing', color: 'accent', icon: '📧', bg: 'bg-accent/10' },
    tracking: { label: 'Ad Tracking', color: 'blue-600', icon: '📊', bg: 'bg-blue-100' },
    campaign: { label: 'Campanie Ads', color: 'purple-600', icon: '📢', bg: 'bg-purple-100' }
};

const statusConfig = {
    draft: { label: 'Ciorna', color: 'text-gray-600', bg: 'bg-gray-100' },
    pending_payment: { label: 'Asteapta Plata', color: 'text-amber-600', bg: 'bg-amber-100' },
    processing: { label: 'In Procesare', color: 'text-blue-600', bg: 'bg-blue-100' },
    active: { label: 'Activ', color: 'text-green-600', bg: 'bg-green-100' },
    completed: { label: 'Finalizat', color: 'text-gray-600', bg: 'bg-gray-100' },
    cancelled: { label: 'Anulat', color: 'text-red-600', bg: 'bg-red-100' },
    refunded: { label: 'Rambursat', color: 'text-red-600', bg: 'bg-red-100' }
};

document.addEventListener('DOMContentLoaded', function() {
    if (!orderId || orderId === 'order') {
        showError();
        return;
    }
    loadOrder();
});

async function loadOrder() {
    try {
        const response = await AmbiletAPI.get(`/organizer/services/orders/${orderId}`);
        if (response.success && response.data.order) {
            order = response.data.order;
            renderOrder();

            // Load additional stats if needed
            if (order.service_type === 'email' && order.config?.sent_at) {
                loadEmailStats();
            }
        } else {
            showError();
        }
    } catch (e) {
        console.error('Error loading order:', e);
        showError();
    }
}

function showError() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('error-state').classList.remove('hidden');
}

function renderOrder() {
    const type = serviceTypes[order.service_type] || serviceTypes.featuring;
    const status = statusConfig[order.status] || statusConfig.draft;

    // Hide loading, show content
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('order-content').classList.remove('hidden');

    // Header
    document.getElementById('order-number').textContent = '#' + (order.order_number || order.id);
    document.getElementById('order-date').textContent = 'Creat pe ' + AmbiletUtils.formatDateTime(order.created_at);

    // Action buttons
    if (order.status === 'pending_payment') {
        document.getElementById('btn-pay').classList.remove('hidden');
        document.getElementById('btn-cancel').classList.remove('hidden');
    }
    if (order.payment_status === 'paid' && order.invoice_url) {
        document.getElementById('btn-invoice').href = order.invoice_url;
        document.getElementById('btn-invoice').classList.remove('hidden');
    }

    // Status banner
    document.getElementById('status-banner').className = `rounded-2xl p-6 ${type.bg}`;
    document.getElementById('status-icon').innerHTML = type.icon;
    document.getElementById('status-icon').className = `w-14 h-14 rounded-xl flex items-center justify-center text-2xl ${status.bg}`;
    document.getElementById('service-type-label').textContent = type.label;
    document.getElementById('status-label').innerHTML = `Status: <span class="font-semibold ${status.color}">${status.label}</span>`;
    document.getElementById('order-total').textContent = AmbiletUtils.formatCurrency(order.total);

    // Event info
    if (order.event) {
        document.getElementById('event-image').src = order.event.image || '/assets/images/default-event.png';
        document.getElementById('event-title').textContent = order.event.title;
        document.getElementById('event-date').textContent = AmbiletUtils.formatDate(order.event.date);
        document.getElementById('event-venue').textContent = order.event.venue || '';
        document.getElementById('event-link').href = '/evenimente/' + (order.event.slug || order.event.id);
    }

    // Configuration
    renderConfiguration();

    // Payment details
    document.getElementById('payment-method').textContent = order.payment_method === 'card' ? 'Card Bancar (Netopia)' : 'Transfer Bancar';
    const paymentStatus = order.payment_status === 'paid' ?
        '<span class="bg-green-100 text-green-600">Platit</span>' :
        '<span class="bg-amber-100 text-amber-600">In Asteptare</span>';
    document.getElementById('payment-status').innerHTML = paymentStatus;

    if (order.paid_at) {
        document.getElementById('payment-date-row').classList.remove('hidden');
        document.getElementById('payment-date').textContent = AmbiletUtils.formatDateTime(order.paid_at);
    }
    if (order.payment_reference) {
        document.getElementById('payment-ref-row').classList.remove('hidden');
        document.getElementById('payment-ref').textContent = order.payment_reference;
    }

    document.getElementById('payment-subtotal').textContent = AmbiletUtils.formatCurrency(order.subtotal || order.total / 1.19);
    document.getElementById('payment-tax').textContent = AmbiletUtils.formatCurrency(order.tax || order.total * 0.19 / 1.19);
    document.getElementById('payment-total').textContent = AmbiletUtils.formatCurrency(order.total);

    // Timeline
    renderTimeline();

    // Service-specific cards
    if (order.service_type === 'email') {
        document.getElementById('email-stats-card').classList.remove('hidden');
        document.getElementById('email-actions-card').classList.remove('hidden');

        if (order.status === 'active' && !order.config?.sent_at) {
            document.getElementById('btn-send-email').href = `/organizator/services/email-send?order=${order.id}`;
            document.getElementById('btn-send-email').classList.remove('hidden');
        }

        if (order.config?.sent_at) {
            document.getElementById('stats-pending').classList.add('hidden');
            document.getElementById('stats-content').classList.remove('hidden');
        } else {
            document.getElementById('stats-pending').classList.remove('hidden');
            document.getElementById('stats-content').classList.add('hidden');
        }
    }

    if (order.service_type === 'featuring' && order.status === 'active') {
        document.getElementById('featuring-stats-card').classList.remove('hidden');
        if (order.featuring_stats) {
            document.getElementById('feat-impressions').textContent = AmbiletUtils.formatNumber(order.featuring_stats.impressions || 0);
            document.getElementById('feat-clicks').textContent = AmbiletUtils.formatNumber(order.featuring_stats.clicks || 0);
            const ctr = order.featuring_stats.impressions > 0 ?
                ((order.featuring_stats.clicks / order.featuring_stats.impressions) * 100).toFixed(2) : 0;
            document.getElementById('feat-ctr').textContent = ctr + '%';
        }
    }
}

function renderConfiguration() {
    let html = '';
    const config = order.config || {};

    switch (order.service_type) {
        case 'featuring':
            const locationLabels = { home: 'Pagina Principala', category: 'Pagina Categorie', genre: 'Pagina Gen', city: 'Pagina Oras' };
            const locations = (config.locations || []).map(l => locationLabels[l] || l);
            html = `
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-muted mb-1">Locatii Promovare</p>
                        <div class="flex flex-wrap gap-2">
                            ${locations.map(l => `<span class="px-3 py-1 bg-primary/10 text-primary text-sm rounded-full">${l}</span>`).join('')}
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted mb-1">Data Inceput</p>
                            <p class="font-medium text-secondary">${AmbiletUtils.formatDate(config.start_date)}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted mb-1">Data Sfarsit</p>
                            <p class="font-medium text-secondary">${AmbiletUtils.formatDate(config.end_date)}</p>
                        </div>
                    </div>
                </div>
            `;
            break;

        case 'email':
            const audienceLabels = { own: 'Clientii Tai', marketplace: 'Baza Marketplace', all: 'Baza Completa', filtered: 'Audienta Filtrata' };
            const pricePerEmail = config.price_per_email || (config.audience_type === 'own' ? 0.40 : 0.50);
            html = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted mb-1">Tip Audienta</p>
                            <p class="font-medium text-secondary">${audienceLabels[config.audience_type] || audienceLabels[config.audience] || 'N/A'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted mb-1">Nr. Destinatari</p>
                            <p class="font-medium text-secondary">${AmbiletUtils.formatNumber(config.recipient_count || 0)}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted mb-1">Cost per Email</p>
                            <p class="font-medium text-secondary">${AmbiletUtils.formatCurrency(pricePerEmail)}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted mb-1">Data Programata</p>
                            <p class="font-medium text-secondary">${config.send_date ? AmbiletUtils.formatDateTime(config.send_date) : 'Neprogramat'}</p>
                        </div>
                    </div>
                    ${config.filters && Object.keys(config.filters).some(k => config.filters[k]) ? `
                        <div>
                            <p class="text-sm text-muted mb-2">Filtre Aplicate</p>
                            <div class="flex flex-wrap gap-2">
                                ${config.filters.age_min ? `<span class="px-2 py-1 bg-surface text-muted text-xs rounded">Varsta min: ${config.filters.age_min}</span>` : ''}
                                ${config.filters.age_max ? `<span class="px-2 py-1 bg-surface text-muted text-xs rounded">Varsta max: ${config.filters.age_max}</span>` : ''}
                                ${config.filters.city ? `<span class="px-2 py-1 bg-surface text-muted text-xs rounded">Oras: ${config.filters.city}</span>` : ''}
                                ${config.filters.category ? `<span class="px-2 py-1 bg-surface text-muted text-xs rounded">Categorie: ${config.filters.category}</span>` : ''}
                                ${config.filters.genre ? `<span class="px-2 py-1 bg-surface text-muted text-xs rounded">Gen: ${config.filters.genre}</span>` : ''}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
            break;

        case 'tracking':
            const platformLabels = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
            const platforms = (config.platforms || []).map(p => platformLabels[p] || p);
            html = `
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-muted mb-1">Platforme Active</p>
                        <div class="flex flex-wrap gap-2">
                            ${platforms.map(p => `<span class="px-3 py-1 bg-blue-100 text-blue-600 text-sm rounded-full">${p}</span>`).join('')}
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted mb-1">Durata</p>
                            <p class="font-medium text-secondary">${config.duration_months || config.duration || 1} luni</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted mb-1">Expira</p>
                            <p class="font-medium text-secondary">${order.service_end_date ? AmbiletUtils.formatDate(order.service_end_date) : 'N/A'}</p>
                        </div>
                    </div>
                    ${config.pixel_ids ? `
                        <div>
                            <p class="text-sm text-muted mb-2">ID-uri Pixel</p>
                            <div class="bg-surface rounded-lg p-3 font-mono text-sm">
                                ${Object.entries(config.pixel_ids).map(([k, v]) => `<p>${k}: ${v}</p>`).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
            break;

        case 'campaign':
            const typeLabels = { basic: 'Basic', standard: 'Standard', premium: 'Premium' };
            html = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted mb-1">Pachet</p>
                            <p class="font-medium text-secondary">Campanie ${typeLabels[config.campaign_type] || 'Standard'}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted mb-1">Buget Publicitar</p>
                            <p class="font-medium text-secondary">${AmbiletUtils.formatCurrency(config.ad_budget || 0)}</p>
                        </div>
                    </div>
                    ${config.platforms ? `
                        <div>
                            <p class="text-sm text-muted mb-1">Platforme</p>
                            <div class="flex flex-wrap gap-2">
                                ${(config.platforms || []).map(p => `<span class="px-3 py-1 bg-purple-100 text-purple-600 text-sm rounded-full">${p}</span>`).join('')}
                            </div>
                        </div>
                    ` : ''}
                    ${config.notes ? `
                        <div>
                            <p class="text-sm text-muted mb-1">Note</p>
                            <p class="text-secondary">${config.notes}</p>
                        </div>
                    ` : ''}
                </div>
            `;
            break;
    }

    document.getElementById('config-content').innerHTML = html;
}

function renderTimeline() {
    const timeline = order.timeline || [];
    const container = document.getElementById('timeline');

    if (!timeline.length) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <p>Niciun eveniment inregistrat</p>
            </div>
        `;
        return;
    }

    container.innerHTML = timeline.map((item, index) => {
        const isLast = index === timeline.length - 1;
        const dotColor = item.type === 'success' ? 'bg-green-500' : item.type === 'error' ? 'bg-red-500' : 'bg-gray-400';

        return `
            <div class="flex gap-4">
                <div class="flex flex-col items-center">
                    <div class="w-3 h-3 rounded-full ${dotColor}"></div>
                    ${!isLast ? '<div class="w-0.5 flex-1 bg-border mt-1"></div>' : ''}
                </div>
                <div class="flex-1 pb-4">
                    <p class="text-sm text-secondary">${item.message}</p>
                    <p class="text-xs text-muted">${AmbiletUtils.formatDateTime(item.created_at)}</p>
                </div>
            </div>
        `;
    }).join('');
}

async function loadEmailStats() {
    try {
        const response = await AmbiletAPI.get(`/organizer/services/orders/${orderId}/email-stats`);
        if (response.success && response.data) {
            updateEmailStats(response.data);
        }
    } catch (e) {
        console.log('Email stats not available yet');
    }
}

function updateEmailStats(stats) {
    document.getElementById('stats-pending').classList.add('hidden');
    document.getElementById('stats-content').classList.remove('hidden');

    document.getElementById('stat-delivered').textContent = AmbiletUtils.formatNumber(stats.delivered || 0);
    document.getElementById('stat-opens').textContent = AmbiletUtils.formatNumber(stats.unique_opens || stats.opens || 0);
    document.getElementById('stat-clicks').textContent = AmbiletUtils.formatNumber(stats.unique_clicks || stats.clicks || 0);
    document.getElementById('stat-unsubscribes').textContent = AmbiletUtils.formatNumber(stats.unsubscribed || 0);
    document.getElementById('stat-bounced').textContent = AmbiletUtils.formatNumber(stats.bounced || stats.hard_bounces + stats.soft_bounces || 0);
    document.getElementById('stat-complaints').textContent = AmbiletUtils.formatNumber(stats.complaints || stats.spam_complaints || 0);

    const openRate = stats.open_rate || (stats.delivered > 0 ? ((stats.unique_opens || stats.opens || 0) / stats.delivered * 100) : 0);
    const clickRate = stats.click_rate || (stats.delivered > 0 ? ((stats.unique_clicks || stats.clicks || 0) / stats.delivered * 100) : 0);

    document.getElementById('stat-open-rate').textContent = openRate.toFixed(1) + '%';
    document.getElementById('stat-click-rate').textContent = clickRate.toFixed(1) + '%';
    document.getElementById('bar-open-rate').style.width = Math.min(openRate, 100) + '%';
    document.getElementById('bar-click-rate').style.width = Math.min(clickRate, 100) + '%';

    if (stats.sent_at || order.config?.sent_at) {
        document.getElementById('stat-sent-at').textContent = AmbiletUtils.formatDateTime(stats.sent_at || order.config.sent_at);
    }
}

async function refreshEmailStats() {
    AmbiletNotifications.info('Se actualizeaza statisticile...');
    await loadEmailStats();
    AmbiletNotifications.success('Statistici actualizate!');
}

async function payOrder() {
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

async function cancelOrder() {
    if (!confirm('Esti sigur ca vrei sa anulezi aceasta comanda?')) return;

    try {
        const response = await AmbiletAPI.post(`/organizer/services/orders/${orderId}/cancel`);
        if (response.success) {
            AmbiletNotifications.success('Comanda a fost anulata');
            loadOrder(); // Reload to show updated status
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la anularea comenzii');
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la anularea comenzii');
    }
}

function previewEmail() {
    // Open email preview in a new window or modal
    window.open(`/organizator/services/email-preview?order=${orderId}`, '_blank', 'width=700,height=600');
}
</script>
JS;
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
