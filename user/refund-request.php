<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Cerere rambursare';
$currentPage = 'orders';
$cssBundle = 'account';
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
                <span class="font-medium text-secondary">Cerere rambursare</span>
            </nav>

            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-secondary">Cerere rambursare</h1>
                <p class="mt-1 text-sm text-muted">Completeaza formularul de mai jos pentru a solicita rambursarea comenzii tale.</p>
            </div>

            <!-- Loading State -->
            <div id="loading-state" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-10 h-10 mb-4 border-4 rounded-full border-primary/30 border-t-primary animate-spin"></div>
                <p class="text-muted">Se incarca detaliile comenzii...</p>
            </div>

            <!-- Error State -->
            <div id="error-state" class="hidden">
                <div class="p-6 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-red-50">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-secondary" id="error-title">Eroare</h3>
                    <p class="mb-4 text-sm text-muted" id="error-message">A aparut o eroare.</p>
                    <a href="/cont/comenzi" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition-colors rounded-lg bg-primary hover:bg-primary/90">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Inapoi la comenzi
                    </a>
                </div>
            </div>

            <!-- Already Requested State -->
            <div id="already-requested-state" class="hidden">
                <div class="p-6 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-amber-50">
                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-secondary">Cerere existenta</h3>
                    <p class="mb-1 text-sm text-muted">Exista deja o cerere de rambursare activa pentru aceasta comanda.</p>
                    <p class="mb-4 text-sm font-medium text-amber-600" id="existing-request-info"></p>
                    <a href="/cont/comenzi" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition-colors rounded-lg bg-primary hover:bg-primary/90">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Inapoi la comenzi
                    </a>
                </div>
            </div>

            <!-- Success State -->
            <div id="success-state" class="hidden">
                <div class="p-6 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-green-50">
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-secondary">Cererea a fost trimisa!</h3>
                    <p class="mb-1 text-sm text-muted">Cererea ta de rambursare a fost inregistrata cu succes.</p>
                    <p class="mb-4 text-sm font-medium text-green-600" id="success-request-number"></p>
                    <p class="mb-6 text-sm text-muted">Vei fi notificat prin email despre statusul cererii tale.</p>
                    <a href="/cont/comenzi" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition-colors rounded-lg bg-primary hover:bg-primary/90">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Inapoi la comenzi
                    </a>
                </div>
            </div>

            <!-- Refund Form -->
            <div id="refund-form-container" class="hidden space-y-6">
                <!-- Order Summary Card -->
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-5 border-b border-border">
                        <h2 class="font-semibold text-secondary">Detalii comanda</h2>
                    </div>
                    <div class="p-5">
                        <div class="flex items-start gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-primary/10">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-secondary" id="order-number">---</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700" id="order-status">---</span>
                                </div>
                                <p class="text-sm text-muted" id="order-event-name">---</p>
                                <div class="flex flex-wrap gap-4 mt-2 text-sm text-muted">
                                    <span id="order-date">---</span>
                                    <span class="font-semibold text-secondary" id="order-total">---</span>
                                    <span id="order-tickets-count">---</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warnings -->
                <div id="warnings-container" class="hidden">
                    <div class="flex gap-3 p-4 border rounded-xl bg-amber-50 border-amber-200">
                        <svg class="flex-shrink-0 w-5 h-5 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <div class="text-sm text-amber-700" id="warnings-text"></div>
                    </div>
                </div>

                <!-- Refund Form Card -->
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-5 border-b border-border">
                        <h2 class="font-semibold text-secondary">Detalii cerere</h2>
                    </div>
                    <div class="p-5 space-y-5">
                        <!-- Refund Type -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary">Tip rambursare</label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 p-4 transition-colors border cursor-pointer rounded-xl border-border hover:border-primary/50" id="type-full">
                                    <input type="radio" name="refund_type" value="full_refund" class="w-4 h-4 text-primary focus:ring-primary" checked>
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Rambursare completa</p>
                                        <p class="text-xs text-muted">Suma integrala a comenzii</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-4 transition-colors border cursor-pointer rounded-xl border-border hover:border-primary/50" id="type-cancel">
                                    <input type="radio" name="refund_type" value="cancellation" class="w-4 h-4 text-primary focus:ring-primary">
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Anulare comanda</p>
                                        <p class="text-xs text-muted">Anuleaza si ramburseaza</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary" for="refund-reason">Motivul rambursarii</label>
                            <select id="refund-reason" class="w-full px-4 py-3 text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">Selecteaza motivul...</option>
                            </select>
                        </div>

                        <!-- Customer Notes -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary" for="customer-notes">Detalii suplimentare <span class="font-normal text-muted">(optional)</span></label>
                            <textarea id="customer-notes" rows="4" maxlength="1000" class="w-full px-4 py-3 text-sm border resize-none bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Descrie motivul pentru care doresti rambursarea..."></textarea>
                            <p class="mt-1 text-xs text-muted"><span id="notes-count">0</span>/1000 caractere</p>
                        </div>

                        <!-- Refund Amount Summary -->
                        <div class="p-4 border border-dashed rounded-xl bg-surface border-border">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted">Suma solicitata:</span>
                                <span class="text-lg font-bold text-secondary" id="refund-amount">---</span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:justify-between">
                            <a href="/cont/comenzi" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold transition-colors border rounded-xl border-border text-muted hover:text-secondary hover:border-secondary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Anuleaza
                            </a>
                            <button id="submit-btn" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-semibold text-white transition-colors rounded-xl bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                Trimite cererea de rambursare
                            </button>
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
const RefundRequestPage = {
    orderId: null,
    orderData: null,
    reasons: [],
    isSubmitting: false,

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return;
        }

        const params = new URLSearchParams(window.location.search);
        this.orderId = params.get('order');
        const preselectedReason = params.get('reason') || '';

        if (!this.orderId) {
            this.showError('Comanda lipsa', 'Nu a fost specificat un numar de comanda valid.');
            return;
        }

        this.setupListeners();
        this.loadData(preselectedReason);
    },

    setupListeners() {
        document.getElementById('submit-btn').addEventListener('click', () => this.submitRequest());
        document.getElementById('customer-notes').addEventListener('input', (e) => {
            document.getElementById('notes-count').textContent = e.target.value.length;
        });
    },

    async loadData(preselectedReason) {
        try {
            // Load eligibility check and reasons in parallel
            const [eligibilityRes, reasonsRes, orderRes] = await Promise.all([
                AmbiletAPI.customer.checkRefundEligibility(this.orderId),
                AmbiletAPI.customer.getRefundReasons(),
                AmbiletAPI.customer.getOrder(this.orderId)
            ]);

            // Check eligibility
            const eligData = eligibilityRes.data || eligibilityRes;
            if (!eligData.eligible) {
                if (eligData.existing_request) {
                    this.showAlreadyRequested(eligData.existing_request);
                } else {
                    this.showError('Rambursare indisponibila', eligData.reason || 'Aceasta comanda nu poate fi rambursata.');
                }
                return;
            }

            // Store order data
            const orderWrapper = orderRes.data || orderRes;
            this.orderData = orderWrapper.order || orderWrapper;

            // Populate reasons
            const reasonsData = reasonsRes.data || reasonsRes;
            this.reasons = reasonsData.reasons || [];
            this.populateReasons(preselectedReason);

            // Populate order summary
            this.populateOrderSummary(this.orderData, eligData);

            // Show warnings if any
            if (eligData.warnings && eligData.warnings.length > 0) {
                const warningsContainer = document.getElementById('warnings-container');
                document.getElementById('warnings-text').innerHTML = eligData.warnings.map(w => '<p>' + w + '</p>').join('');
                warningsContainer.classList.remove('hidden');
            }

            // Show form
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('refund-form-container').classList.remove('hidden');

        } catch (err) {
            console.error('Failed to load refund data:', err);
            this.showError('Eroare', err.message || 'Nu am putut incarca detaliile comenzii.');
        }
    },

    populateReasons(preselectedReason) {
        const select = document.getElementById('refund-reason');
        const reasonLabels = {
            'event_cancelled': 'Evenimentul a fost anulat',
            'event_postponed': 'Evenimentul a fost amanat',
            'cannot_attend': 'Nu pot participa la eveniment',
            'wrong_tickets': 'Am achizitionat bilete gresite',
            'duplicate_purchase': 'Achizitie duplicata',
            'technical_issue': 'Problema tehnica la achizitie',
            'other': 'Alt motiv'
        };

        this.reasons.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = reasonLabels[r.value] || r.label;
            if (r.value === preselectedReason) opt.selected = true;
            select.appendChild(opt);
        });

        // If reason from URL maps to a known reason like "ticket_refundable", map it
        if (preselectedReason && !select.value) {
            const mappings = {
                'ticket_refundable': 'cannot_attend',
                'event_cancelled': 'event_cancelled',
                'event_postponed': 'event_postponed'
            };
            const mapped = mappings[preselectedReason];
            if (mapped) select.value = mapped;
        }
    },

    populateOrderSummary(order, eligData) {
        document.getElementById('order-number').textContent = '#' + (order.order_number || order.id);

        const statusLabels = {
            'completed': 'Finalizata', 'paid': 'Platita', 'confirmed': 'Confirmata',
            'pending': 'In asteptare', 'cancelled': 'Anulata'
        };
        document.getElementById('order-status').textContent = statusLabels[order.status] || order.status;

        document.getElementById('order-event-name').textContent = order.event_name || order.items?.[0]?.event_name || '---';

        if (order.created_at) {
            const d = new Date(order.created_at);
            const months = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Nov','Dec'];
            document.getElementById('order-date').textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        }

        const total = order.total || eligData.order?.total || 0;
        const currency = order.currency || eligData.order?.currency || 'RON';
        const formattedTotal = new Intl.NumberFormat('ro-RO', { style: 'currency', currency: currency }).format(total);
        document.getElementById('order-total').textContent = formattedTotal;
        document.getElementById('refund-amount').textContent = formattedTotal;

        const ticketsCount = order.tickets_count || order.items?.reduce((sum, item) => sum + (item.quantity || 1), 0) || 0;
        if (ticketsCount > 0) {
            document.getElementById('order-tickets-count').textContent = ticketsCount + (ticketsCount === 1 ? ' bilet' : ' bilete');
        }
    },

    showError(title, message) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-title').textContent = title;
        document.getElementById('error-message').textContent = message;
        document.getElementById('error-state').classList.remove('hidden');
    },

    showAlreadyRequested(existingRequest) {
        document.getElementById('loading-state').classList.add('hidden');
        const statusLabels = {
            'pending': 'In asteptare', 'under_review': 'In analiza',
            'approved': 'Aprobata', 'processing': 'In procesare'
        };
        document.getElementById('existing-request-info').textContent =
            'Cererea #' + (existingRequest.request_number || existingRequest.id) +
            ' - Status: ' + (statusLabels[existingRequest.status] || existingRequest.status);
        document.getElementById('already-requested-state').classList.remove('hidden');
    },

    async submitRequest() {
        if (this.isSubmitting) return;

        const reason = document.getElementById('refund-reason').value;
        if (!reason) {
            AmbiletNotifications.warning('Selecteaza un motiv pentru rambursare.');
            return;
        }

        const refundType = document.querySelector('input[name="refund_type"]:checked')?.value || 'full_refund';
        const customerNotes = document.getElementById('customer-notes').value.trim();

        this.isSubmitting = true;
        const submitBtn = document.getElementById('submit-btn');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div> Se trimite...';

        try {
            const res = await AmbiletAPI.customer.submitRefundRequest({
                order_id: parseInt(this.orderId),
                type: refundType,
                reason: reason,
                customer_notes: customerNotes || null
            });

            const data = res.data || res;
            const refundRequest = data.refund_request || {};

            document.getElementById('success-request-number').textContent =
                'Numar cerere: #' + (refundRequest.request_number || refundRequest.id || '---');

            document.getElementById('refund-form-container').classList.add('hidden');
            document.getElementById('success-state').classList.remove('hidden');

        } catch (err) {
            console.error('Refund request failed:', err);
            AmbiletNotifications.error(err.message || 'Nu am putut trimite cererea. Incearca din nou.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
            this.isSubmitting = false;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => RefundRequestPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
