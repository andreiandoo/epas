<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Detalii Comanda';
$currentPage = 'orders';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6 text-sm">
                <a href="/cont" class="text-muted hover:text-primary">Contul meu</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="/cont/comenzi" class="text-muted hover:text-primary">Comenzi</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-medium text-secondary" id="order-number-breadcrumb">---</span>
            </nav>

            <!-- Page Header -->
            <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Comanda <span id="order-number-title">#---</span></h1>
                    <div class="flex flex-wrap items-center gap-3 mt-2">
                        <span class="text-sm text-muted" id="order-date">---</span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700" id="order-status">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span id="order-status-text">---</span>
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="/cont/bilete" class="hidden btn btn-primary" id="view-tickets-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        Vezi biletele
                    </a>
                    <button class="hidden btn btn-secondary" id="download-pdf-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarca PDF
                    </button>
                    <button class="hidden inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-red-600 transition-colors border border-red-300 rounded-lg hover:bg-red-50 hover:border-red-400" id="refund-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Returneaza comanda
                    </button>
                </div>
            </div>

            <!-- Order Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column (2/3) -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Event & Tickets Card -->
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-semibold text-secondary">Bilete comandate</h2>
                        </div>
                        <div class="p-5" id="order-items">
                            <!-- Items will be loaded here -->
                        </div>
                    </div>

                    <!-- Order Timeline -->
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-semibold text-secondary">Istoric comanda</h2>
                        </div>
                        <div class="p-5">
                            <div class="relative pl-6" id="order-timeline">
                                <!-- Timeline will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Download (hidden by default, shown for confirmed orders) -->
                    <div class="hidden overflow-hidden bg-white border rounded-2xl border-border" id="invoice-section">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-semibold text-secondary">Documente</h2>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center justify-between p-4 border border-dashed rounded-xl bg-surface border-border">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 bg-white border rounded-lg border-border">
                                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-secondary">Factura fiscala</h4>
                                        <p class="text-xs text-muted" id="invoice-filename">---</p>
                                    </div>
                                </div>
                                <button class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-white border rounded-lg border-border text-secondary hover:border-primary hover:text-primary transition-colors" id="download-invoice-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Descarca
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (1/3) -->
                <div class="space-y-6">
                    <!-- Order Summary -->
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-semibold text-secondary">Sumar comanda</h2>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between py-3 text-sm">
                                <span class="text-muted">Subtotal (<span id="tickets-count">0</span> bilete)</span>
                                <span class="font-medium text-secondary" id="subtotal">0 RON</span>
                            </div>
                            <div class="flex justify-between py-3 text-sm">
                                <span class="text-muted">Taxa servicii</span>
                                <span class="font-medium text-secondary" id="service-fee">0 RON</span>
                            </div>
                            <div class="flex justify-between py-3 text-sm" id="discount-row" style="display: none;">
                                <span class="text-muted">Discount aplicat</span>
                                <span class="font-medium text-green-600" id="discount">-0 RON</span>
                            </div>
                            <div class="flex justify-between pt-4 mt-2 text-lg font-bold border-t border-border">
                                <span class="text-secondary">Total platit</span>
                                <span class="text-primary" id="total">0 RON</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-semibold text-secondary">Metoda de plata</h2>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center gap-3 p-3 mb-4 rounded-lg bg-surface">
                                <div class="flex items-center justify-center w-12 h-8 bg-white border rounded border-border" id="payment-icon">
                                    <span class="text-xs font-bold text-blue-900">VISA</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-secondary" id="payment-method">---</h4>
                                    <p class="text-xs text-muted" id="payment-exp">---</p>
                                </div>
                            </div>
                            <div class="text-sm text-muted" id="billing-address">
                                <strong class="block mb-1 font-semibold text-secondary">Adresa de facturare</strong>
                                ---
                            </div>
                        </div>
                    </div>

                    <!-- Support (hidden by default, shown for confirmed orders) -->
                    <div class="hidden overflow-hidden bg-white border rounded-2xl border-border" id="support-section">
                        <div class="p-5">
                            <div class="p-5 text-center text-white rounded-xl bg-gradient-to-br from-secondary to-gray-900">
                                <h4 class="mb-2 font-semibold">Ai nevoie de ajutor?</h4>
                                <p class="mb-4 text-sm text-gray-400">Suntem aici sa te ajutam cu orice intrebare despre comanda.</p>
                                <a href="/cont/ajutor" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-white rounded-lg text-secondary hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    Contacteaza suport
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const OrderDetailPage = {
    orderId: null,

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=' + window.location.pathname;
            return;
        }

        this.orderId = this.getOrderIdFromUrl();
        if (this.orderId) {
            this.loadOrder();
        } else {
            AmbiletNotifications.error('Comanda nu a fost gasita.');
            window.location.href = '/cont/comenzi';
        }

        this.setupEventListeners();
    },

    getOrderIdFromUrl() {
        const pathParts = window.location.pathname.split('/');
        return pathParts[pathParts.length - 1] || null;
    },

    setupEventListeners() {
        document.getElementById('download-pdf-btn').addEventListener('click', () => this.downloadPdf());
        document.getElementById('download-invoice-btn').addEventListener('click', () => this.downloadInvoice());
        document.getElementById('refund-btn').addEventListener('click', () => this.requestRefund());
    },

    async loadOrder() {
        try {
            const response = await AmbiletAPI.get('/customer/orders/' + this.orderId);
            if (response.success && response.order) {
                this.renderOrder(response.order);
            } else {
                AmbiletNotifications.error('Comanda nu a fost gasita.');
                window.location.href = '/cont/comenzi';
            }
        } catch (error) {
            console.error('Error loading order:', error);
            AmbiletNotifications.error('Eroare la incarcarea comenzii.');
        }
    },

    renderOrder(order) {
        // Update header
        document.getElementById('order-number-breadcrumb').textContent = '#' + order.number;
        document.getElementById('order-number-title').textContent = '#' + order.number;
        document.getElementById('order-date').textContent = 'Plasata pe ' + order.date;

        // Update status
        const statusEl = document.getElementById('order-status');
        const statusText = document.getElementById('order-status-text');
        statusText.textContent = order.status_label;

        const statusClasses = {
            'confirmed': 'bg-green-100 text-green-700',
            'pending': 'bg-yellow-100 text-yellow-700',
            'cancelled': 'bg-red-100 text-red-700',
            'refunded': 'bg-gray-100 text-gray-700'
        };
        statusEl.className = 'inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full ' + (statusClasses[order.status] || statusClasses.confirmed);

        // Render items
        this.renderItems(order.items);

        // Render timeline
        this.renderTimeline(order.timeline);

        // Update summary
        document.getElementById('tickets-count').textContent = order.tickets_count;
        document.getElementById('subtotal').textContent = order.subtotal + ' RON';
        document.getElementById('service-fee').textContent = order.service_fee + ' RON';

        if (order.discount && order.discount > 0) {
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('discount').textContent = '-' + order.discount + ' RON';
        }

        document.getElementById('total').textContent = order.total + ' RON';

        // Update payment
        document.getElementById('payment-method').textContent = order.payment_method;
        document.getElementById('payment-exp').textContent = order.payment_exp || '';
        document.getElementById('billing-address').innerHTML = '<strong class="block mb-1 font-semibold text-secondary">Adresa de facturare</strong>' + order.billing_address;

        // Update invoice
        document.getElementById('invoice-filename').textContent = order.invoice_filename || 'Nu exista factura';

        // Update view tickets link
        document.getElementById('view-tickets-btn').href = '/cont/bilete?order=' + order.number;

        // Show/hide refund button
        const refundBtn = document.getElementById('refund-btn');
        console.log('Refund eligibility:', { can_request_refund: order.can_request_refund, refund_reason: order.refund_reason });
        if (order.can_request_refund) {
            refundBtn.classList.remove('hidden');
            // Store refund reason for later use
            this.refundReason = order.refund_reason;
        } else {
            refundBtn.classList.add('hidden');
        }

        // Show/hide sections based on order status (only for confirmed/paid/completed)
        const isPaidOrder = ['confirmed', 'paid', 'completed'].includes(order.status) || order.can_download_tickets;

        // View tickets and Download PDF buttons
        if (isPaidOrder) {
            document.getElementById('view-tickets-btn').classList.remove('hidden');
            document.getElementById('download-pdf-btn').classList.remove('hidden');
        }

        // Invoice section (only for paid orders)
        if (isPaidOrder) {
            document.getElementById('invoice-section').classList.remove('hidden');
        }

        // Support section (only for paid orders)
        if (isPaidOrder) {
            document.getElementById('support-section').classList.remove('hidden');
        }
    },

    renderItems(items) {
        const container = document.getElementById('order-items');
        container.innerHTML = items.map(item => `
            <div class="flex gap-5 pb-5 mb-5 border-b border-border last:border-0 last:pb-0 last:mb-0">
                <div class="flex items-center justify-center flex-shrink-0 w-28 h-28 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500">
                    ${item.image ? `<img src="${item.image}" alt="${item.event_title}" class="object-cover w-full h-full rounded-xl">` : `
                    <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    `}
                </div>
                <div class="flex-1">
                    <h3 class="mb-2 font-bold text-secondary">
                        <a href="/eveniment/${item.event_slug}" class="hover:text-primary">${item.event_title}</a>
                    </h3>
                    <div class="space-y-1 mb-4">
                        <div class="flex items-center gap-2 text-sm text-muted">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            ${item.event_date}
                        </div>
                        <div class="flex items-center gap-2 text-sm text-muted">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            ${item.venue}
                        </div>
                    </div>
                    <div class="space-y-2">
                        ${item.tickets.map(ticket => `
                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 text-xs font-semibold text-white rounded bg-gradient-to-r from-primary to-primary/80">${ticket.type}</span>
                                <span class="font-medium text-secondary">${ticket.name}</span>
                                <span class="text-sm text-muted">x ${ticket.quantity}</span>
                            </div>
                            <span class="font-semibold text-secondary">${ticket.price} RON</span>
                        </div>
                        `).join('')}
                    </div>
                    <a href="/cont/bilete?order=${this.orderId}" class="inline-flex items-center gap-2 mt-4 text-sm font-semibold text-primary">
                        Vezi si descarca biletele
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        `).join('');
    },

    renderTimeline(timeline) {
        const container = document.getElementById('order-timeline');
        container.innerHTML = `
            <div class="absolute left-1 top-2 bottom-2 w-0.5 bg-border"></div>
            ${timeline.map((item, index) => `
            <div class="relative pb-5 last:pb-0">
                <div class="absolute left-[-1.5rem] top-1 w-3 h-3 rounded-full border-2 border-white ${item.completed ? 'bg-green-500 shadow-[0_0_0_2px_#D1FAE5]' : item.current ? 'bg-primary shadow-[0_0_0_2px_#FEE2E2]' : 'bg-border shadow-[0_0_0_2px_#E2E8F0]'}"></div>
                <div>
                    <h4 class="text-sm font-semibold text-secondary">${item.title}</h4>
                    <p class="text-xs text-muted">${item.date}</p>
                </div>
            </div>
            `).join('')}
        `;
    },

    downloadPdf() {
        AmbiletNotifications.info('Descarcarea PDF va incepe in curand...');
        // window.location.href = '/api/v1/customer/orders/' + this.orderId + '/pdf';
    },

    downloadInvoice() {
        AmbiletNotifications.info('Descarcarea facturii va incepe in curand...');
        // window.location.href = '/api/v1/customer/orders/' + this.orderId + '/invoice';
    },

    requestRefund() {
        // Get a human-readable reason message
        let reasonText = '';
        switch (this.refundReason) {
            case 'event_cancelled':
                reasonText = 'Evenimentul a fost anulat';
                break;
            case 'event_postponed':
                reasonText = 'Evenimentul a fost amanat';
                break;
            case 'ticket_refundable':
                reasonText = 'Biletele sunt returnabile';
                break;
            default:
                reasonText = 'Cerere de rambursare';
        }

        // Navigate to refund request page with order info
        window.location.href = '/cont/cerere-rambursare?order=' + this.orderId + '&reason=' + encodeURIComponent(this.refundReason);
    }
};

document.addEventListener('DOMContentLoaded', () => OrderDetailPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
