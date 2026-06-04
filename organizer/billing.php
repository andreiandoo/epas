<?php
/**
 * bilete.online — Organizator › Facturare (v3).
 * Route: /organizator/facturare
 *
 * Invoice history + payout (decont) history with a top toggle, billing-info
 * card, invoice detail modal and PDF/CSV export. Ported from ambilet to v3 +
 * shell, wired to BileteOnlineAPI.organizer (getPayouts) and the
 * organizer.invoices / organizer.invoice(.pdf) / organizer.invoices.export /
 * organizer.billing-info / organizer.payouts / organizer.payout proxy actions.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Facturare';
$currentPage = 'billing';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Facturi și deconturi</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Gestionează facturile, deconturile și datele de facturare.</p>
            </div>
            <button onclick="BillingManager.exportInvoices()" class="inline-flex items-center gap-2 self-start rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper sm:self-auto">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Exportă
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left column -->
            <div class="lg:col-span-2">
                <!-- Section toggle -->
                <div class="mb-4 inline-flex gap-1 rounded-full border-2 border-ink bg-paper p-1" role="tablist">
                    <button type="button" onclick="BillingManager.setSection('invoices')" data-section-tab="invoices" class="section-tab rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition" role="tab" aria-selected="true">Istoric facturi</button>
                    <button type="button" onclick="BillingManager.setSection('payouts')" data-section-tab="payouts" class="section-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" role="tab" aria-selected="false">Istoric deconturi</button>
                </div>

                <!-- Invoices -->
                <div id="invoices-section" class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                    <div class="flex flex-col gap-3 border-b-2 border-ink/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-display text-lg font-bold">Istoric facturi</h2>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="BillingManager.setFilter('all')" data-filter="all" class="filter-tab rounded-full bg-vermilion px-3 py-1.5 text-sm font-bold text-paper">Toate</button>
                            <button onclick="BillingManager.setFilter('paid')" data-filter="paid" class="filter-tab rounded-full bg-paper-2 px-3 py-1.5 text-sm font-bold text-ink-soft hover:text-ink">Plătite</button>
                            <button onclick="BillingManager.setFilter('pending')" data-filter="pending" class="filter-tab rounded-full bg-paper-2 px-3 py-1.5 text-sm font-bold text-ink-soft hover:text-ink">În așteptare</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-paper-2 text-left">
                                <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                    <th class="px-5 py-3">Nr. factură</th>
                                    <th class="px-5 py-3">Dată</th>
                                    <th class="hidden px-5 py-3 md:table-cell">Descriere</th>
                                    <th class="px-5 py-3">Sumă</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-table-body" class="divide-y divide-ink/10 text-sm"></tbody>
                        </table>
                    </div>
                    <div id="empty-state" class="hidden py-12 text-center">
                        <svg class="mx-auto mb-3 h-14 w-14 text-ink/15" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <h3 class="font-display text-lg font-bold">Nicio factură</h3>
                        <p class="text-sm text-ink-soft">Nu ai nicio factură încă.</p>
                    </div>
                    <div id="loading-state" class="py-12 text-center text-ink-soft">Se încarcă…</div>
                    <div id="pagination" class="flex items-center justify-between border-t-2 border-ink/10 p-4">
                        <span id="pagination-info" class="text-sm text-ink-soft">Afișare 0-0 din 0 facturi</span>
                        <div id="pagination-buttons" class="flex gap-1"></div>
                    </div>
                </div>

                <!-- Payouts -->
                <div id="payouts-section" class="hidden overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                    <div class="flex flex-col gap-3 border-b-2 border-ink/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-display text-lg font-bold">Istoric deconturi</h2>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="PayoutManager.setFilter('all')" data-payout-filter="all" class="payout-filter-tab rounded-full bg-vermilion px-3 py-1.5 text-sm font-bold text-paper">Toate</button>
                            <button onclick="PayoutManager.setFilter('completed')" data-payout-filter="completed" class="payout-filter-tab rounded-full bg-paper-2 px-3 py-1.5 text-sm font-bold text-ink-soft hover:text-ink">Finalizate</button>
                            <button onclick="PayoutManager.setFilter('pending')" data-payout-filter="pending" class="payout-filter-tab rounded-full bg-paper-2 px-3 py-1.5 text-sm font-bold text-ink-soft hover:text-ink">În așteptare</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-paper-2 text-left">
                                <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                    <th class="px-5 py-3">Referință</th>
                                    <th class="px-5 py-3">Dată</th>
                                    <th class="px-5 py-3">Activitate</th>
                                    <th class="px-5 py-3">Valoare</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody id="payouts-table-body" class="divide-y divide-ink/10 text-sm"></tbody>
                        </table>
                    </div>
                    <div id="payouts-empty-state" class="hidden py-12 text-center">
                        <svg class="mx-auto mb-3 h-14 w-14 text-ink/15" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <h3 class="font-display text-lg font-bold">Niciun decont</h3>
                        <p class="text-sm text-ink-soft">Nu ai niciun decont încă.</p>
                    </div>
                    <div id="payouts-loading-state" class="py-12 text-center text-ink-soft">Se încarcă…</div>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="space-y-6">
                <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                    <div class="flex items-center justify-between border-b-2 border-ink/10 p-4">
                        <h2 class="font-display text-base font-bold">Date facturare</h2>
                        <button onclick="BillingManager.editBillingInfo()" class="rounded-full border-2 border-ink px-3 py-1.5 text-xs font-bold transition hover:bg-ink hover:text-paper">Editează</button>
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="flex justify-between gap-3 border-b border-ink/10 py-2.5"><span class="text-sm text-ink-soft">Nume companie</span><span id="billing-company" class="text-right text-sm font-bold">-</span></div>
                        <div class="flex justify-between gap-3 border-b border-ink/10 py-2.5"><span class="text-sm text-ink-soft">CUI</span><span id="billing-cui" class="text-right text-sm font-bold">-</span></div>
                        <div class="flex justify-between gap-3 border-b border-ink/10 py-2.5"><span class="text-sm text-ink-soft">Nr. Reg. Com.</span><span id="billing-reg" class="text-right text-sm font-bold">-</span></div>
                        <div class="flex justify-between gap-3 border-b border-ink/10 py-2.5"><span class="text-sm text-ink-soft">Adresă</span><span id="billing-address" class="max-w-[160px] text-right text-sm font-bold">-</span></div>
                        <div class="flex justify-between gap-3 py-2.5"><span class="text-sm text-ink-soft">Email facturare</span><span id="billing-email" class="text-right text-sm font-bold">-</span></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Invoice / payout detail modal -->
<div id="invoice-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" onclick="BillingManager.hideInvoiceModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-auto rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="flex items-center justify-between border-b-2 border-ink/10 p-6">
                <div>
                    <h3 class="font-display text-lg font-bold">Detalii factură</h3>
                    <p id="modal-invoice-number" class="font-mono text-sm text-ink-soft">-</p>
                </div>
                <button onclick="BillingManager.hideInvoiceModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
            </div>
            <div id="invoice-modal-content" class="p-6"></div>
            <div class="flex justify-end gap-3 border-t-2 border-ink/10 p-6">
                <button onclick="BillingManager.hideInvoiceModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Închide</button>
                <button id="modal-download-btn" onclick="BillingManager.downloadInvoice()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarcă PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
const SITE_NAME = 'bilete.online';

function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}
function orgToken() { try { return (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? (BileteOnlineAuth.getToken() || '') : ''; } catch (e) { return ''; } }
function proxyBase() { return (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php'; }

const BillingManager = {
    invoices: [], billingInfo: null, currentFilter: 'all', currentPage: 1, perPage: 10, totalInvoices: 0, currentInvoice: null,

    async init() { await Promise.all([this.loadInvoices(), this.loadBillingInfo()]); },

    setSection(section) {
        const inv = document.getElementById('invoices-section');
        const pay = document.getElementById('payouts-section');
        if (!inv || !pay) return;
        if (section === 'payouts') { inv.classList.add('hidden'); pay.classList.remove('hidden'); }
        else { pay.classList.add('hidden'); inv.classList.remove('hidden'); }
        document.querySelectorAll('.section-tab').forEach(btn => {
            const active = btn.dataset.sectionTab === section;
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.className = 'section-tab rounded-full px-4 py-2 text-sm font-bold transition ' + (active ? 'bg-vermilion text-paper' : 'text-ink-soft hover:text-ink');
        });
    },

    async loadInvoices() {
        document.getElementById('loading-state').classList.remove('hidden');
        try {
            const params = { page: this.currentPage, per_page: this.perPage };
            if (this.currentFilter !== 'all') params.status = this.currentFilter;
            const r = await BileteOnlineAPI.get('/organizer/invoices', params);
            if (r && r.success) {
                this.invoices = (r.data && r.data.invoices) || [];
                this.totalInvoices = (r.data && r.data.total) || 0;
                this.renderInvoices();
                this.renderPagination();
            }
        } catch (e) { orgNotify('Nu s-au putut încărca facturile.', 'error'); }
        finally { document.getElementById('loading-state').classList.add('hidden'); }
    },

    async loadBillingInfo() {
        try {
            const r = await BileteOnlineAPI.get('/organizer/billing-info');
            if (r && r.success) { this.billingInfo = r.data; this.renderBillingInfo(); }
        } catch (e) {}
    },

    renderBillingInfo() {
        const b = this.billingInfo || {};
        document.getElementById('billing-company').textContent = b.company_name || '-';
        document.getElementById('billing-cui').textContent = b.cui || '-';
        document.getElementById('billing-reg').textContent = b.reg_number || '-';
        document.getElementById('billing-address').textContent = b.address || '-';
        document.getElementById('billing-email').textContent = b.email || '-';
    },

    renderInvoices() {
        const tbody = document.getElementById('invoices-table-body');
        const empty = document.getElementById('empty-state');
        if (!this.invoices.length) { tbody.innerHTML = ''; empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');
        tbody.innerHTML = this.invoices.map(inv => `
            <tr class="hover:bg-paper-2/60">
                <td class="px-5 py-4"><span class="font-mono text-sm font-bold">${this.esc(inv.number)}</span></td>
                <td class="px-5 py-4 text-sm text-ink-soft">${this.fmtDate(inv.date)}</td>
                <td class="hidden px-5 py-4 text-sm md:table-cell">${this.esc(inv.description)}</td>
                <td class="px-5 py-4 text-sm font-bold">${this.money(inv.amount)}</td>
                <td class="px-5 py-4">${this.statusBadge(inv.status)}</td>
                <td class="px-5 py-4"><div class="flex justify-end gap-2">
                    <button onclick="BillingManager.viewInvoice('${inv.id}')" title="Vizualizează" class="grid h-8 w-8 place-items-center rounded-lg border-2 border-ink/15 text-ink-soft transition hover:border-ink hover:text-ink"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                    <button onclick="BillingManager.downloadInvoicePdf('${inv.id}')" title="Descarcă" class="grid h-8 w-8 place-items-center rounded-lg border-2 border-ink/15 text-ink-soft transition hover:border-ink hover:text-ink"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></button>
                </div></td>
            </tr>`).join('');
    },

    renderPagination() {
        const totalPages = Math.ceil(this.totalInvoices / this.perPage);
        const container = document.getElementById('pagination-buttons');
        const info = document.getElementById('pagination-info');
        const start = this.totalInvoices ? (this.currentPage - 1) * this.perPage + 1 : 0;
        const end = Math.min(this.currentPage * this.perPage, this.totalInvoices);
        info.textContent = `Afișare ${start}-${end} din ${this.totalInvoices} facturi`;
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        const btn = (label, page, disabled, active) =>
            `<button onclick="BillingManager.goToPage(${page})" ${disabled ? 'disabled' : ''} class="grid h-8 min-w-8 place-items-center rounded-lg border-2 px-2 text-sm font-bold transition ${active ? 'border-ink bg-vermilion text-paper' : 'border-ink/15 text-ink-soft hover:border-ink hover:text-ink'} ${disabled ? 'cursor-not-allowed opacity-40' : ''}">${label}</button>`;
        let html = btn('‹', this.currentPage - 1, this.currentPage === 1, false);
        this.getPageNumbers(totalPages).forEach(p => { html += (p === '...') ? '<span class="grid h-8 w-8 place-items-center text-sm text-ink-soft">…</span>' : btn(p, p, false, p === this.currentPage); });
        html += btn('›', this.currentPage + 1, this.currentPage === totalPages, false);
        container.innerHTML = html;
    },

    getPageNumbers(total) {
        const c = this.currentPage, pages = [];
        if (total <= 7) { for (let i = 1; i <= total; i++) pages.push(i); }
        else {
            pages.push(1); if (c > 3) pages.push('...');
            for (let i = Math.max(2, c - 1); i <= Math.min(total - 1, c + 1); i++) pages.push(i);
            if (c < total - 2) pages.push('...'); pages.push(total);
        }
        return pages;
    },

    goToPage(page) {
        const total = Math.ceil(this.totalInvoices / this.perPage);
        if (page < 1 || page > total) return;
        this.currentPage = page; this.loadInvoices();
    },

    setFilter(filter) {
        this.currentFilter = filter; this.currentPage = 1;
        document.querySelectorAll('.filter-tab').forEach(btn => {
            const active = btn.dataset.filter === filter;
            btn.className = 'filter-tab rounded-full px-3 py-1.5 text-sm font-bold transition ' + (active ? 'bg-vermilion text-paper' : 'bg-paper-2 text-ink-soft hover:text-ink');
        });
        this.loadInvoices();
    },

    async viewInvoice(id) {
        try {
            const r = await BileteOnlineAPI.get(`/organizer/invoices/${id}`);
            if (r && r.success) {
                this.currentInvoice = r.data;
                document.getElementById('modal-download-btn').classList.remove('hidden');
                this.renderInvoiceModal(r.data);
                document.getElementById('invoice-modal').classList.remove('hidden');
            }
        } catch (e) { orgNotify('Nu s-a putut încărca factura.', 'error'); }
    },

    renderInvoiceModal(inv) {
        document.getElementById('modal-invoice-number').textContent = inv.number || '-';
        const iss = inv.issuer || {}, cli = inv.client || {};
        const line = (cond, txt) => cond ? `<p class="text-sm text-ink-soft">${txt}</p>` : '';
        document.getElementById('invoice-modal-content').innerHTML = `
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h4 class="mb-2 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Emitent</h4>
                    <p class="text-sm font-bold">${this.esc(iss.name || SITE_NAME)}</p>
                    ${line(iss.cui, 'CUI: ' + this.esc(iss.cui))}
                    ${line(iss.reg_com, 'Reg. Com.: ' + this.esc(iss.reg_com))}
                    ${line(iss.address, this.esc(iss.address))}
                    ${line(iss.bank_name && iss.iban, this.esc(iss.bank_name) + ': ' + this.esc(iss.iban))}
                    ${line(iss.email, this.esc(iss.email))}
                </div>
                <div>
                    <h4 class="mb-2 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Client</h4>
                    <p class="text-sm font-bold">${this.esc(cli.name || '')}</p>
                    ${line(cli.address, this.esc(cli.address))}
                    ${line(cli.cui, 'CUI: ' + this.esc(cli.cui))}
                </div>
            </div>
            <div class="mt-6 border-t-2 border-ink/10 pt-6">
                <table class="w-full">
                    <thead><tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft"><th class="pb-3 text-left">Descriere</th><th class="pb-3 text-right">Cant.</th><th class="pb-3 text-right">Preț</th><th class="pb-3 text-right">Total</th></tr></thead>
                    <tbody>${(inv.items || []).map(it => `<tr class="border-t border-ink/10"><td class="py-3 text-sm">${this.esc(it.description)}</td><td class="py-3 text-right text-sm text-ink-soft">${it.quantity}</td><td class="py-3 text-right text-sm text-ink-soft">${this.money(it.price)}</td><td class="py-3 text-right text-sm font-bold">${this.money(it.total)}</td></tr>`).join('')}</tbody>
                    <tfoot class="border-t-2 border-ink/10">
                        <tr><td colspan="3" class="pt-3 text-right text-sm font-bold">Subtotal:</td><td class="pt-3 text-right text-sm font-bold">${this.money(inv.subtotal)}</td></tr>
                        ${inv.vat ? `<tr><td colspan="3" class="pt-2 text-right text-sm text-ink-soft">TVA (${inv.vat_rate}%):</td><td class="pt-2 text-right text-sm text-ink-soft">${this.money(inv.vat)}</td></tr>` : ''}
                        <tr><td colspan="3" class="pt-3 text-right font-display text-lg font-bold">Total:</td><td class="pt-3 text-right font-display text-lg font-bold text-vermilion">${this.money(inv.total)}</td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-6 flex items-center justify-between border-t-2 border-ink/10 pt-6">
                <div class="flex items-center gap-2"><span class="text-sm text-ink-soft">Status:</span>${this.statusBadge(inv.status)}</div>
                <div class="text-sm text-ink-soft">Emisă: ${this.fmtDate(inv.date)} · Scadentă: ${this.fmtDate(inv.due_date)}</div>
            </div>`;
    },

    hideInvoiceModal() { document.getElementById('invoice-modal').classList.add('hidden'); this.currentInvoice = null; },
    downloadInvoice() { if (this.currentInvoice && this.currentInvoice.id) this.downloadInvoicePdf(this.currentInvoice.id); },
    downloadInvoicePdf(id) { window.open(proxyBase() + `?action=organizer.invoice.pdf&invoice_id=${encodeURIComponent(id)}&token=${encodeURIComponent(orgToken())}`, '_blank'); },
    exportInvoices() { window.open(proxyBase() + `?action=organizer.invoices.export&status=${encodeURIComponent(this.currentFilter)}&token=${encodeURIComponent(orgToken())}`, '_blank'); },
    editBillingInfo() { window.location.href = '/organizator/setari#company'; },

    statusBadge(status) {
        const m = {
            paid: ['bg-forest/15 text-forest', 'bg-forest', 'Plătită'],
            pending: ['bg-ochre/15 text-ochre', 'bg-ochre', 'În așteptare'],
            overdue: ['bg-vermilion/15 text-vermilion', 'bg-vermilion', 'Restantă'],
        };
        const s = m[status] || m.pending;
        return `<span class="inline-flex items-center gap-1.5 rounded-full ${s[0]} px-2.5 py-1 text-xs font-bold"><span class="h-1.5 w-1.5 rounded-full ${s[1]}"></span>${s[2]}</span>`;
    },
    money(a) { try { return BileteOnlineUtils.formatCurrency(a); } catch (e) { return (a || 0) + ' RON'; } },
    fmtDate(d) { if (!d) return '-'; try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d; } },
    esc(t) { const d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; },
};

const PayoutManager = {
    payouts: [], currentFilter: 'all',

    async init() { await this.loadPayouts(); },

    async loadPayouts() {
        try {
            const r = await BileteOnlineAPI.organizer.getPayouts({ per_page: 50 });
            if (r && r.success) { this.payouts = (r.data && (r.data.data || r.data.payouts)) || (Array.isArray(r.data) ? r.data : []); this.render(); }
        } catch (e) {}
        finally { document.getElementById('payouts-loading-state').classList.add('hidden'); }
    },

    setFilter(filter) {
        this.currentFilter = filter;
        document.querySelectorAll('.payout-filter-tab').forEach(btn => {
            const active = btn.dataset.payoutFilter === filter;
            btn.className = 'payout-filter-tab rounded-full px-3 py-1.5 text-sm font-bold transition ' + (active ? 'bg-vermilion text-paper' : 'bg-paper-2 text-ink-soft hover:text-ink');
        });
        this.render();
    },

    getFiltered() {
        if (this.currentFilter === 'completed') return this.payouts.filter(p => p.status === 'completed');
        if (this.currentFilter === 'pending') return this.payouts.filter(p => ['pending', 'approved', 'processing'].includes(p.status));
        return this.payouts;
    },

    render() {
        const tbody = document.getElementById('payouts-table-body');
        const empty = document.getElementById('payouts-empty-state');
        const filtered = this.getFiltered();
        if (!filtered.length) { tbody.innerHTML = ''; empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');
        const m = {
            pending: ['bg-ochre/15 text-ochre', 'În așteptare'], approved: ['bg-sky/15 text-sky', 'Aprobat'],
            processing: ['bg-sky/15 text-sky', 'În procesare'], completed: ['bg-forest/15 text-forest', 'Finalizat'],
            rejected: ['bg-vermilion/15 text-vermilion', 'Respins'], cancelled: ['bg-ink/10 text-ink-soft', 'Anulat'],
        };
        tbody.innerHTML = filtered.map(p => {
            const st = m[p.status] || m.pending;
            const title = p.event_title || (p.event && p.event.title) || '-';
            return `<tr class="hover:bg-paper-2/60">
                <td class="px-5 py-4"><span class="font-mono text-sm font-bold">${this.esc(p.reference || ('#' + p.id))}</span></td>
                <td class="px-5 py-4 text-sm text-ink-soft">${this.fmtDate(p.created_at)}</td>
                <td class="px-5 py-4 text-sm">${this.esc(title)}</td>
                <td class="px-5 py-4 text-sm font-bold">${this.money(p.amount)}</td>
                <td class="px-5 py-4"><span class="rounded-full ${st[0]} px-2.5 py-1 text-xs font-bold">${st[1]}</span></td>
                <td class="px-5 py-4"><div class="flex justify-end gap-2">
                    <button onclick="PayoutManager.viewPayout(${p.id})" title="Vizualizează" class="grid h-8 w-8 place-items-center rounded-lg border-2 border-ink/15 text-ink-soft transition hover:border-ink hover:text-ink"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                </div></td>
            </tr>`;
        }).join('');
    },

    async viewPayout(id) {
        try {
            const r = await BileteOnlineAPI.get(`/organizer/payouts/${id}`);
            if (r && r.success) {
                const p = (r.data && r.data.payout) || r.data;
                const labels = { pending: 'În așteptare', approved: 'Aprobat', processing: 'În procesare', completed: 'Finalizat', rejected: 'Respins', cancelled: 'Anulat' };
                const row = (k, v) => `<div class="flex justify-between gap-3 border-b border-ink/10 py-3"><span class="text-sm text-ink-soft">${k}</span><span class="text-right text-sm font-bold">${v}</span></div>`;
                document.getElementById('modal-invoice-number').textContent = 'Decont ' + (p.reference || ('#' + p.id));
                document.getElementById('modal-download-btn').classList.add('hidden');
                document.getElementById('invoice-modal-content').innerHTML = `<div>
                    ${row('Referință', this.esc(p.reference || ('#' + p.id)))}
                    ${row('Dată', this.fmtDate(p.created_at))}
                    ${row('Activitate', this.esc(p.event_title || '-'))}
                    ${row('Valoare', this.money(p.amount || 0))}
                    ${row('Status', labels[p.status] || this.esc(p.status))}
                    ${row('Cont bancar', this.esc(p.account || (p.payout_method && p.payout_method.iban) || '-'))}
                    ${p.period_start ? row('Perioadă', this.esc(p.period_start) + ' — ' + this.esc(p.period_end || '-')) : ''}
                    ${p.rejection_reason ? `<div class="mt-3 rounded-xl bg-vermilion/10 p-3 text-sm text-vermilion"><strong>Motiv respingere:</strong> ${this.esc(p.rejection_reason)}</div>` : ''}
                    ${p.notes ? `<div class="mt-3 rounded-xl bg-paper-2 p-3 text-sm text-ink-soft"><strong>Note:</strong> ${this.esc(p.notes)}</div>` : ''}
                </div>`;
                document.getElementById('invoice-modal').classList.remove('hidden');
            }
        } catch (e) { orgNotify('Eroare la încărcarea decontului.', 'error'); }
    },

    money(a) { try { return BileteOnlineUtils.formatCurrency(a); } catch (e) { return (a || 0) + ' RON'; } },
    fmtDate(d) { if (!d) return '-'; try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d; } },
    esc(t) { const d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => { BillingManager.init(); PayoutManager.init(); });
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
