<?php
/**
 * Organizer Billing Page
 *
 * Invoice listing and billing management
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Facturi și Facturare';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'billing';

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Facturi și Facturare</h1>
                <p class="mt-1 text-sm text-muted">Gestionează facturile, plățile și detaliile de facturare</p>
            </div>
            <div class="flex gap-3">
                <button onclick="BillingManager.exportInvoices()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold transition-all border rounded-lg border-border text-muted hover:bg-slate-50 hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exportă
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-green-50">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p id="stat-total-paid" class="text-2xl font-bold text-secondary">0 RON</p>
                <p class="text-sm text-muted">Total încasat</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-blue-50">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p id="stat-total-invoices" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-sm text-muted">Facturi emise</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-yellow-50">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p id="stat-pending" class="text-2xl font-bold text-secondary">0 RON</p>
                <p class="text-sm text-muted">În așteptare</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-red-50">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p id="stat-commission" class="text-2xl font-bold text-secondary">0%</p>
                <p class="text-sm text-muted">Comision mediu</p>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Invoices Table -->
            <div class="overflow-hidden bg-white border lg:col-span-2 rounded-xl border-border">
                <div class="flex flex-col gap-4 p-4 border-b sm:flex-row sm:items-center sm:justify-between border-border">
                    <h2 class="text-lg font-semibold text-secondary">Istoric facturi</h2>
                    <div class="flex gap-2">
                        <button onclick="BillingManager.setFilter('all')" data-filter="all" class="filter-tab px-3 py-1.5 text-sm font-medium rounded-lg bg-primary text-white">
                            Toate
                        </button>
                        <button onclick="BillingManager.setFilter('paid')" data-filter="paid" class="filter-tab px-3 py-1.5 text-sm font-medium rounded-lg bg-slate-100 text-muted hover:bg-slate-200">
                            Plătite
                        </button>
                        <button onclick="BillingManager.setFilter('pending')" data-filter="pending" class="filter-tab px-3 py-1.5 text-sm font-medium rounded-lg bg-slate-100 text-muted hover:bg-slate-200">
                            În așteptare
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Nr. Factură</th>
                                <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Dată</th>
                                <th class="hidden px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase md:table-cell text-muted">Descriere</th>
                                <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Sumă</th>
                                <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Status</th>
                                <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody id="invoices-table-body">
                            <!-- Loaded via JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="hidden py-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="mb-2 text-lg font-semibold text-secondary">Nicio factură</h3>
                    <p class="text-sm text-muted">Nu ai nicio factură încă</p>
                </div>

                <!-- Loading State -->
                <div id="loading-state" class="py-12 text-center">
                    <div class="inline-flex items-center gap-2 text-muted">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Se încarcă...
                    </div>
                </div>

                <!-- Pagination -->
                <div id="pagination" class="flex items-center justify-between p-4 border-t border-border">
                    <span id="pagination-info" class="text-sm text-muted">Afișare 0-0 din 0 facturi</span>
                    <div id="pagination-buttons" class="flex gap-1">
                        <!-- Generated via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Billing Info -->
                <div class="overflow-hidden bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h2 class="text-base font-semibold text-secondary">Date facturare</h2>
                        <button onclick="BillingManager.editBillingInfo()" class="px-3 py-1.5 text-xs font-semibold border rounded-lg border-border text-muted hover:bg-slate-50">
                            Editează
                        </button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-muted">Nume companie</span>
                            <span id="billing-company" class="text-sm font-semibold text-secondary">-</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-muted">CUI</span>
                            <span id="billing-cui" class="text-sm font-semibold text-secondary">-</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-muted">Nr. Reg. Com.</span>
                            <span id="billing-reg" class="text-sm font-semibold text-secondary">-</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-muted">Adresă</span>
                            <span id="billing-address" class="text-sm font-semibold text-right text-secondary max-w-[150px]">-</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-sm text-muted">Email facturare</span>
                            <span id="billing-email" class="text-sm font-semibold text-secondary">-</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="overflow-hidden bg-white border rounded-xl border-border">
                    <div class="p-4 border-b border-border">
                        <h2 class="text-base font-semibold text-secondary">Metode de plată</h2>
                    </div>
                    <div class="p-4 space-y-3">
                        <div id="payment-methods-list">
                            <!-- Loaded via JavaScript -->
                        </div>

                        <button onclick="BillingManager.addPaymentMethod()" class="flex items-center justify-center w-full gap-2 p-3 text-sm font-medium transition-colors border-2 border-dashed rounded-lg border-border text-muted hover:border-primary hover:text-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Adaugă metodă de plată
                        </button>

                        <!-- Next Invoice -->
                        <div id="next-invoice" class="hidden p-4 mt-4 text-white rounded-xl bg-gradient-to-br from-secondary to-slate-900">
                            <h4 class="text-sm font-semibold">Următoarea factură</h4>
                            <p id="next-invoice-amount" class="text-2xl font-bold">0,00 RON</p>
                            <p id="next-invoice-date" class="text-sm text-slate-400">Scadentă pe -</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Invoice Detail Modal -->
<div id="invoice-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="BillingManager.hideInvoiceModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl bg-white shadow-xl rounded-2xl max-h-[90vh] overflow-auto">
            <div class="flex items-center justify-between p-6 border-b border-border">
                <div>
                    <h3 class="text-lg font-semibold text-secondary">Detalii factură</h3>
                    <p id="modal-invoice-number" class="text-sm font-mono text-muted">-</p>
                </div>
                <button onclick="BillingManager.hideInvoiceModal()" class="p-2 transition-colors rounded-lg text-muted hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="invoice-modal-content" class="p-6">
                <!-- Loaded via JavaScript -->
            </div>
            <div class="flex justify-end gap-3 p-6 border-t border-border">
                <button onclick="BillingManager.hideInvoiceModal()" class="px-4 py-2.5 text-sm font-semibold border rounded-lg border-border text-muted hover:bg-slate-50">
                    Închide
                </button>
                <button onclick="BillingManager.downloadInvoice()" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarcă PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

<script>
/**
 * Billing Management Module
 */
const BillingManager = {
    invoices: [],
    billingInfo: null,
    paymentMethods: [],
    currentFilter: 'all',
    currentPage: 1,
    perPage: 10,
    totalInvoices: 0,
    currentInvoice: null,

    /**
     * Initialize the billing manager
     */
    async init() {
        await Promise.all([
            this.loadInvoices(),
            this.loadBillingInfo(),
            this.loadPaymentMethods()
        ]);
    },

    /**
     * Load invoices
     */
    async loadInvoices() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                status: this.currentFilter !== 'all' ? this.currentFilter : ''
            });

            const response = await AmbiletAPI.request(`/organizer/invoices?${params}`);
            if (response.success) {
                this.invoices = response.data.invoices || [];
                this.totalInvoices = response.data.total || 0;
                this.updateStats(response.data.stats);
                this.renderInvoices();
                this.renderPagination();
            }
        } catch (error) {
            console.error('Error loading invoices:', error);
            this.showError('Nu s-au putut încărca facturile');
        } finally {
            document.getElementById('loading-state').classList.add('hidden');
        }
    },

    /**
     * Load billing info
     */
    async loadBillingInfo() {
        try {
            const response = await AmbiletAPI.request('/organizer/billing-info');
            if (response.success) {
                this.billingInfo = response.data;
                this.renderBillingInfo();
            }
        } catch (error) {
            console.error('Error loading billing info:', error);
        }
    },

    /**
     * Load payment methods
     */
    async loadPaymentMethods() {
        try {
            const response = await AmbiletAPI.request('/organizer/payment-methods');
            if (response.success) {
                this.paymentMethods = response.data.methods || [];
                this.renderPaymentMethods();

                // Show next invoice if available
                if (response.data.next_invoice) {
                    this.renderNextInvoice(response.data.next_invoice);
                }
            }
        } catch (error) {
            console.error('Error loading payment methods:', error);
        }
    },

    /**
     * Update stats
     */
    updateStats(stats) {
        if (!stats) return;

        document.getElementById('stat-total-paid').textContent = this.formatCurrency(stats.total_paid || 0);
        document.getElementById('stat-total-invoices').textContent = stats.total_invoices || 0;
        document.getElementById('stat-pending').textContent = this.formatCurrency(stats.pending_amount || 0);
        document.getElementById('stat-commission').textContent = `${stats.avg_commission || 0}%`;
    },

    /**
     * Render invoices table
     */
    renderInvoices() {
        const tbody = document.getElementById('invoices-table-body');
        const emptyState = document.getElementById('empty-state');

        if (this.invoices.length === 0) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        tbody.innerHTML = this.invoices.map(invoice => this.renderInvoiceRow(invoice)).join('');
    },

    /**
     * Render a single invoice row
     */
    renderInvoiceRow(invoice) {
        const statusBadge = this.getStatusBadge(invoice.status);
        const isPending = invoice.status === 'pending';

        return `
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-4">
                    <span class="font-mono text-sm font-semibold text-secondary">${this.escapeHtml(invoice.number)}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="text-sm text-muted">${this.formatDate(invoice.date)}</span>
                </td>
                <td class="hidden px-6 py-4 md:table-cell">
                    <span class="text-sm text-secondary">${this.escapeHtml(invoice.description)}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="text-sm font-bold text-secondary">${this.formatCurrency(invoice.amount)}</span>
                </td>
                <td class="px-6 py-4">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4">
                    <div class="flex gap-2">
                        <button onclick="BillingManager.viewInvoice('${invoice.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-primary hover:text-primary" title="Vizualizează">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                        ${isPending ? `
                            <button onclick="BillingManager.payInvoice('${invoice.id}')" class="flex items-center justify-center w-8 h-8 text-white transition-colors rounded-lg bg-primary hover:bg-primary-dark" title="Plătește">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </button>
                        ` : `
                            <button onclick="BillingManager.downloadInvoicePdf('${invoice.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-primary hover:text-primary" title="Descarcă">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    },

    /**
     * Render billing info
     */
    renderBillingInfo() {
        if (!this.billingInfo) return;

        document.getElementById('billing-company').textContent = this.billingInfo.company_name || '-';
        document.getElementById('billing-cui').textContent = this.billingInfo.cui || '-';
        document.getElementById('billing-reg').textContent = this.billingInfo.reg_number || '-';
        document.getElementById('billing-address').textContent = this.billingInfo.address || '-';
        document.getElementById('billing-email').textContent = this.billingInfo.email || '-';
    },

    /**
     * Render payment methods
     */
    renderPaymentMethods() {
        const container = document.getElementById('payment-methods-list');

        if (this.paymentMethods.length === 0) {
            container.innerHTML = '<p class="text-sm text-center text-muted">Nicio metodă de plată salvată</p>';
            return;
        }

        container.innerHTML = this.paymentMethods.map(method => `
            <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-50">
                <div class="flex items-center justify-center w-12 h-8 bg-white border rounded border-border">
                    ${this.getCardIcon(method.brand)}
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-secondary">${method.brand} •••• ${method.last4}</p>
                    <p class="text-xs text-muted">Expiră ${method.exp_month}/${method.exp_year}</p>
                </div>
                ${method.is_default ? '<span class="px-2 py-0.5 text-xs font-semibold text-green-700 bg-green-100 rounded">Default</span>' : ''}
            </div>
        `).join('');
    },

    /**
     * Render next invoice card
     */
    renderNextInvoice(nextInvoice) {
        const container = document.getElementById('next-invoice');
        container.classList.remove('hidden');
        document.getElementById('next-invoice-amount').textContent = this.formatCurrency(nextInvoice.amount);
        document.getElementById('next-invoice-date').textContent = `Scadentă pe ${this.formatDate(nextInvoice.due_date)}`;
    },

    /**
     * Render pagination
     */
    renderPagination() {
        const totalPages = Math.ceil(this.totalInvoices / this.perPage);
        const container = document.getElementById('pagination-buttons');
        const info = document.getElementById('pagination-info');

        const start = (this.currentPage - 1) * this.perPage + 1;
        const end = Math.min(this.currentPage * this.perPage, this.totalInvoices);
        info.textContent = `Afișare ${start}-${end} din ${this.totalInvoices} facturi`;

        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let buttons = '';

        // Previous button
        buttons += `
            <button onclick="BillingManager.goToPage(${this.currentPage - 1})"
                    class="flex items-center justify-center w-8 h-8 text-sm border rounded-lg border-border text-muted hover:border-primary hover:text-primary ${this.currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}"
                    ${this.currentPage === 1 ? 'disabled' : ''}>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
        `;

        // Page numbers
        const pages = this.getPageNumbers(totalPages);
        pages.forEach(page => {
            if (page === '...') {
                buttons += '<span class="flex items-center justify-center w-8 h-8 text-sm text-muted">...</span>';
            } else {
                buttons += `
                    <button onclick="BillingManager.goToPage(${page})"
                            class="flex items-center justify-center w-8 h-8 text-sm border rounded-lg ${page === this.currentPage ? 'bg-primary border-primary text-white' : 'border-border text-muted hover:border-primary hover:text-primary'}">
                        ${page}
                    </button>
                `;
            }
        });

        // Next button
        buttons += `
            <button onclick="BillingManager.goToPage(${this.currentPage + 1})"
                    class="flex items-center justify-center w-8 h-8 text-sm border rounded-lg border-border text-muted hover:border-primary hover:text-primary ${this.currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}"
                    ${this.currentPage === totalPages ? 'disabled' : ''}>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        `;

        container.innerHTML = buttons;
    },

    /**
     * Get page numbers for pagination
     */
    getPageNumbers(totalPages) {
        const current = this.currentPage;
        const pages = [];

        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else {
            pages.push(1);
            if (current > 3) pages.push('...');

            const start = Math.max(2, current - 1);
            const end = Math.min(totalPages - 1, current + 1);

            for (let i = start; i <= end; i++) pages.push(i);

            if (current < totalPages - 2) pages.push('...');
            pages.push(totalPages);
        }

        return pages;
    },

    /**
     * Go to page
     */
    goToPage(page) {
        const totalPages = Math.ceil(this.totalInvoices / this.perPage);
        if (page < 1 || page > totalPages) return;

        this.currentPage = page;
        this.loadInvoices();
    },

    /**
     * Set filter
     */
    setFilter(filter) {
        this.currentFilter = filter;
        this.currentPage = 1;

        // Update filter buttons
        document.querySelectorAll('.filter-tab').forEach(btn => {
            const isActive = btn.dataset.filter === filter;
            btn.classList.toggle('bg-primary', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('bg-slate-100', !isActive);
            btn.classList.toggle('text-muted', !isActive);
        });

        this.loadInvoices();
    },

    /**
     * View invoice details
     */
    async viewInvoice(invoiceId) {
        try {
            const response = await AmbiletAPI.request(`/organizer/invoices/${invoiceId}`);
            if (response.success) {
                this.currentInvoice = response.data;
                this.renderInvoiceModal(response.data);
                document.getElementById('invoice-modal').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading invoice:', error);
            this.showError('Nu s-a putut încărca factura');
        }
    },

    /**
     * Render invoice modal content
     */
    renderInvoiceModal(invoice) {
        document.getElementById('modal-invoice-number').textContent = invoice.number;

        const content = document.getElementById('invoice-modal-content');
        content.innerHTML = `
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Emitent</h4>
                    <p class="text-sm font-semibold text-secondary">${SITE_NAME}</p>
                    <p class="text-sm text-muted">${invoice.issuer?.address || ''}</p>
                    <p class="text-sm text-muted">CUI: ${invoice.issuer?.cui || ''}</p>
                </div>
                <div>
                    <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Client</h4>
                    <p class="text-sm font-semibold text-secondary">${this.escapeHtml(invoice.client?.name || '')}</p>
                    <p class="text-sm text-muted">${this.escapeHtml(invoice.client?.address || '')}</p>
                    <p class="text-sm text-muted">CUI: ${invoice.client?.cui || ''}</p>
                </div>
            </div>

            <div class="pt-6 mt-6 border-t border-border">
                <table class="w-full">
                    <thead>
                        <tr class="text-xs font-semibold tracking-wider uppercase text-muted">
                            <th class="pb-3 text-left">Descriere</th>
                            <th class="pb-3 text-right">Cantitate</th>
                            <th class="pb-3 text-right">Preț</th>
                            <th class="pb-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(invoice.items || []).map(item => `
                            <tr class="border-t border-slate-100">
                                <td class="py-3 text-sm text-secondary">${this.escapeHtml(item.description)}</td>
                                <td class="py-3 text-sm text-right text-muted">${item.quantity}</td>
                                <td class="py-3 text-sm text-right text-muted">${this.formatCurrency(item.price)}</td>
                                <td class="py-3 text-sm font-semibold text-right text-secondary">${this.formatCurrency(item.total)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot class="border-t border-border">
                        <tr>
                            <td colspan="3" class="pt-3 text-sm font-semibold text-right text-secondary">Subtotal:</td>
                            <td class="pt-3 text-sm font-semibold text-right text-secondary">${this.formatCurrency(invoice.subtotal)}</td>
                        </tr>
                        ${invoice.vat ? `
                            <tr>
                                <td colspan="3" class="pt-2 text-sm text-right text-muted">TVA (${invoice.vat_rate}%):</td>
                                <td class="pt-2 text-sm text-right text-muted">${this.formatCurrency(invoice.vat)}</td>
                            </tr>
                        ` : ''}
                        <tr>
                            <td colspan="3" class="pt-3 text-lg font-bold text-right text-secondary">Total:</td>
                            <td class="pt-3 text-lg font-bold text-right text-primary">${this.formatCurrency(invoice.total)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex items-center justify-between pt-6 mt-6 border-t border-border">
                <div>
                    <span class="text-sm text-muted">Status:</span>
                    ${this.getStatusBadge(invoice.status)}
                </div>
                <div class="text-sm text-muted">
                    Emisă: ${this.formatDate(invoice.date)} | Scadentă: ${this.formatDate(invoice.due_date)}
                </div>
            </div>
        `;
    },

    /**
     * Hide invoice modal
     */
    hideInvoiceModal() {
        document.getElementById('invoice-modal').classList.add('hidden');
        this.currentInvoice = null;
    },

    /**
     * Download current invoice
     */
    downloadInvoice() {
        if (this.currentInvoice) {
            this.downloadInvoicePdf(this.currentInvoice.id);
        }
    },

    /**
     * Download invoice PDF
     */
    async downloadInvoicePdf(invoiceId) {
        try {
            const token = (typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : '') || '';
            const proxyUrl = `/api/proxy.php?action=organizer.invoice.pdf&invoice_id=${invoiceId}&token=${encodeURIComponent(token)}`;
            window.open(proxyUrl, '_blank');
        } catch (error) {
            console.error('Error downloading invoice:', error);
            this.showError('Nu s-a putut descărca factura');
        }
    },

    /**
     * Pay invoice
     */
    async payInvoice(invoiceId) {
        // This would typically redirect to a payment gateway
        this.showInfo('Redirecționare către pagina de plată...');
        // window.location.href = `/organizer/pay/${invoiceId}`;
    },

    /**
     * Export invoices
     */
    async exportInvoices() {
        try {
            const token = (typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : '') || '';
            window.open(`/api/proxy.php?action=organizer.invoices.export&status=${this.currentFilter}&token=${encodeURIComponent(token)}`, '_blank');
        } catch (error) {
            console.error('Error exporting invoices:', error);
            this.showError('Nu s-au putut exporta facturile');
        }
    },

    /**
     * Edit billing info
     */
    editBillingInfo() {
        window.location.href = '/organizator/settings#billing';
    },

    /**
     * Add payment method
     */
    addPaymentMethod() {
        window.location.href = '/organizator/settings#payment';
    },

    /**
     * Helper functions
     */
    getStatusBadge(status) {
        const badges = {
            paid: '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Plătită</span>',
            pending: '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>În așteptare</span>',
            overdue: '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700"><span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>Restantă</span>'
        };
        return badges[status] || badges.pending;
    },

    getCardIcon(brand) {
        const icons = {
            visa: '<svg viewBox="0 0 32 20" class="w-8 h-5"><rect width="32" height="20" rx="2" fill="#1A1F71"/><text x="16" y="13" font-size="7" fill="white" text-anchor="middle" font-weight="bold">VISA</text></svg>',
            mastercard: '<svg viewBox="0 0 32 20" class="w-8 h-5"><rect width="32" height="20" rx="2" fill="#EB001B"/><circle cx="12" cy="10" r="6" fill="#EB001B"/><circle cx="20" cy="10" r="6" fill="#F79E1B"/></svg>'
        };
        return icons[brand?.toLowerCase()] || '<svg class="w-8 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>';
    },

    formatCurrency(amount) {
        return new Intl.NumberFormat('ro-RO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount) + ' RON';
    },

    formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('ro-RO', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    showSuccess(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.success(message);
        } else {
            alert(message);
        }
    },

    showError(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.error(message);
        } else {
            alert(message);
        }
    },

    showInfo(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.info(message);
        } else {
            alert(message);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => BillingManager.init());

// Site name for modal
const SITE_NAME = '<?= SITE_NAME ?>';
</script>
