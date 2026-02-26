<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Detalii Comanda Serviciu';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
$orderUuid = $_GET['uuid'] ?? '';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <main class="flex-1 p-4 lg:p-8">
            <!-- Loading -->
            <div id="loading" class="flex items-center justify-center py-20">
                <svg class="w-8 h-8 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Content (hidden until loaded) -->
            <div id="content" class="hidden">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <nav class="flex items-center gap-2 text-sm text-muted mb-2">
                            <a href="/organizator/servicii" class="hover:text-primary">Servicii Extra</a>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="text-secondary" id="breadcrumb-number"></span>
                        </nav>
                        <h1 class="text-2xl font-bold text-secondary" id="page-title">Detalii Comanda</h1>
                    </div>
                    <a href="/organizator/servicii" class="btn btn-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                        Inapoi
                    </a>
                </div>

                <!-- Order Info + Status -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Left: Order details -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center" id="type-icon-bg">
                                    <span id="type-icon"></span>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-secondary" id="order-type-label"></h2>
                                    <p class="text-sm text-muted" id="order-number-display"></p>
                                </div>
                            </div>
                            <span id="status-badge" class="px-3 py-1.5 text-sm font-medium rounded-full"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-surface rounded-xl">
                                <p class="text-xs text-muted mb-1">Eveniment</p>
                                <p class="font-semibold text-secondary" id="event-name">-</p>
                            </div>
                            <div class="p-4 bg-surface rounded-xl">
                                <p class="text-xs text-muted mb-1">Detalii</p>
                                <p class="font-semibold text-secondary" id="order-details">-</p>
                            </div>
                            <div class="p-4 bg-surface rounded-xl">
                                <p class="text-xs text-muted mb-1">Creat la</p>
                                <p class="font-semibold text-secondary" id="created-at">-</p>
                            </div>
                            <div class="p-4 bg-surface rounded-xl">
                                <p class="text-xs text-muted mb-1">Perioada</p>
                                <p class="font-semibold text-secondary" id="service-period">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Payment -->
                    <div class="bg-white rounded-2xl border border-border p-6">
                        <h3 class="text-sm font-bold text-secondary mb-4">Plata</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-muted">Subtotal</span>
                                <span class="text-sm font-medium text-secondary" id="subtotal">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-muted">TVA</span>
                                <span class="text-sm font-medium text-secondary" id="tax">-</span>
                            </div>
                            <div class="border-t border-border pt-3 flex justify-between">
                                <span class="text-sm font-bold text-secondary">Total</span>
                                <span class="text-lg font-bold text-primary" id="total">-</span>
                            </div>
                            <div class="pt-2 space-y-2 text-sm text-muted">
                                <div class="flex justify-between">
                                    <span>Metoda</span>
                                    <span id="payment-method">-</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status plata</span>
                                    <span id="payment-status-text">-</span>
                                </div>
                                <div class="flex justify-between" id="paid-at-row">
                                    <span>Platit la</span>
                                    <span id="paid-at">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Campaign Stats (only for email orders) -->
                <div id="email-stats-section" class="hidden mb-6">
                    <div class="bg-white rounded-2xl border border-border p-6">
                        <h3 class="text-lg font-bold text-secondary mb-6">Statistici Campanie Email</h3>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                            <div class="p-4 bg-blue-50 rounded-xl text-center">
                                <p class="text-2xl font-bold text-blue-600" id="stat-sent">0</p>
                                <p class="text-xs text-blue-600/70 mt-1">Trimise</p>
                            </div>
                            <div class="p-4 bg-green-50 rounded-xl text-center">
                                <p class="text-2xl font-bold text-green-600" id="stat-opened">0</p>
                                <p class="text-xs text-green-600/70 mt-1">Deschise</p>
                            </div>
                            <div class="p-4 bg-purple-50 rounded-xl text-center">
                                <p class="text-2xl font-bold text-purple-600" id="stat-clicked">0</p>
                                <p class="text-xs text-purple-600/70 mt-1">Click-uri</p>
                            </div>
                            <div class="p-4 bg-red-50 rounded-xl text-center">
                                <p class="text-2xl font-bold text-red-600" id="stat-failed">0</p>
                                <p class="text-xs text-red-600/70 mt-1">Esuate</p>
                            </div>
                            <div class="p-4 bg-amber-50 rounded-xl text-center">
                                <p class="text-2xl font-bold text-amber-600" id="stat-unsub">0</p>
                                <p class="text-xs text-amber-600/70 mt-1">Dezabonari</p>
                            </div>
                        </div>

                        <!-- Rates -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Open Rate -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-secondary">Rata deschidere</span>
                                    <span class="text-sm font-bold text-green-600" id="open-rate-text">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full transition-all" id="open-rate-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            <!-- Click Rate -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-secondary">Rata click (din deschise)</span>
                                    <span class="text-sm font-bold text-purple-600" id="click-rate-text">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-purple-500 h-3 rounded-full transition-all" id="click-rate-bar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Audience breakdown -->
                        <div class="mt-6 pt-6 border-t border-border">
                            <h4 class="text-sm font-bold text-secondary mb-3">Audienta</h4>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="audience-breakdown">
                                <div class="p-3 bg-surface rounded-xl">
                                    <p class="text-xs text-muted">Tip audienta</p>
                                    <p class="font-semibold text-secondary text-sm" id="audience-type-label">-</p>
                                </div>
                                <div class="p-3 bg-surface rounded-xl">
                                    <p class="text-xs text-muted">Perfect match</p>
                                    <p class="font-semibold text-secondary text-sm" id="perfect-count">-</p>
                                </div>
                                <div class="p-3 bg-surface rounded-xl">
                                    <p class="text-xs text-muted">Partial match</p>
                                    <p class="font-semibold text-secondary text-sm" id="partial-count">-</p>
                                </div>
                                <div class="p-3 bg-surface rounded-xl">
                                    <p class="text-xs text-muted">Template</p>
                                    <p class="font-semibold text-secondary text-sm capitalize" id="email-template">-</p>
                                </div>
                            </div>

                            <!-- Filters used -->
                            <div id="filters-used" class="hidden mt-4">
                                <p class="text-xs text-muted mb-2">Filtre aplicate</p>
                                <div class="flex flex-wrap gap-2" id="filter-tags"></div>
                            </div>
                        </div>

                        <!-- Newsletter status -->
                        <div class="mt-6 pt-6 border-t border-border">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-muted">Status campanie:</span>
                                    <span id="newsletter-status-badge" class="px-2.5 py-1 text-xs font-medium rounded-full"></span>
                                </div>
                                <div class="text-sm text-muted" id="newsletter-dates"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Config (collapsible) -->
                <div class="bg-white rounded-2xl border border-border p-6" id="config-section">
                    <button onclick="document.getElementById('config-json').classList.toggle('hidden')" class="flex items-center gap-2 text-sm font-bold text-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Configuratie Comanda
                    </button>
                    <pre id="config-json" class="hidden mt-4 p-4 bg-surface rounded-xl text-xs text-muted overflow-x-auto"></pre>
                </div>
            </div>

            <!-- Error State -->
            <div id="error-state" class="hidden text-center py-20">
                <svg class="w-16 h-16 text-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h2 class="text-xl font-bold text-secondary mb-2">Comanda nu a fost gasita</h2>
                <p class="text-muted mb-6">Verifica link-ul sau intoarce-te la lista de servicii.</p>
                <a href="/organizator/servicii" class="btn btn-primary">Inapoi la Servicii</a>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

const ORDER_UUID = new URLSearchParams(window.location.search).get('uuid') || window.location.pathname.split('/').pop();

document.addEventListener('DOMContentLoaded', loadOrderDetails);

async function loadOrderDetails() {
    try {
        const response = await AmbiletAPI.get(`/organizer/services/orders/${ORDER_UUID}`);
        if (!response.success || !response.data?.order) {
            throw new Error('Not found');
        }
        renderOrder(response.data.order);
    } catch (e) {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
    }
}

function renderOrder(order) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('content').classList.remove('hidden');

    // Header
    document.getElementById('breadcrumb-number').textContent = order.order_number;
    document.getElementById('page-title').textContent = `${order.type_label} - ${order.order_number}`;

    // Type icon
    const typeIcons = {
        featuring: '<svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
        email: '<svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        tracking: '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        campaign: '<svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>'
    };
    const typeBgColors = { featuring: 'bg-primary/10', email: 'bg-emerald-100', tracking: 'bg-blue-100', campaign: 'bg-purple-100' };
    document.getElementById('type-icon-bg').className = `w-12 h-12 rounded-xl flex items-center justify-center ${typeBgColors[order.type] || 'bg-surface'}`;
    document.getElementById('type-icon').innerHTML = typeIcons[order.type] || '';
    document.getElementById('order-type-label').textContent = order.type_label;
    document.getElementById('order-number-display').textContent = order.order_number;

    // Status badge
    const statusColors = {
        active: 'bg-green-100 text-green-700',
        pending_payment: 'bg-amber-100 text-amber-700',
        processing: 'bg-blue-100 text-blue-700',
        completed: 'bg-gray-100 text-gray-700',
        cancelled: 'bg-red-100 text-red-700',
    };
    const badge = document.getElementById('status-badge');
    badge.textContent = order.status_label;
    badge.className = `px-3 py-1.5 text-sm font-medium rounded-full ${statusColors[order.status] || 'bg-gray-100 text-gray-700'}`;

    // Order info
    document.getElementById('event-name').textContent = order.event?.name || order.event_name || '-';
    document.getElementById('order-details').textContent = order.details || '-';
    document.getElementById('created-at').textContent = fmtDateTime(order.created_at);
    const period = order.service_start_date && order.service_end_date
        ? `${fmtDate(order.service_start_date)} - ${fmtDate(order.service_end_date)}`
        : order.service_start_date ? `Din ${fmtDate(order.service_start_date)}` : '-';
    document.getElementById('service-period').textContent = period;

    // Payment
    document.getElementById('subtotal').textContent = fmtCurrency(order.subtotal) + ' ' + order.currency;
    document.getElementById('tax').textContent = fmtCurrency(order.tax) + ' ' + order.currency;
    document.getElementById('total').textContent = fmtCurrency(order.total) + ' ' + order.currency;
    document.getElementById('payment-method').textContent = order.payment_method === 'card' ? 'Card online' : (order.payment_method === 'transfer' ? 'Transfer bancar' : order.payment_method || '-');
    document.getElementById('payment-status-text').textContent = order.payment_status === 'paid' ? 'Platit' : (order.payment_status || '-');
    document.getElementById('paid-at').textContent = order.paid_at ? fmtDateTime(order.paid_at) : '-';

    // Config JSON
    document.getElementById('config-json').textContent = JSON.stringify(order.config, null, 2);

    // Email Marketing Stats
    if (order.type === 'email') {
        document.getElementById('email-stats-section').classList.remove('hidden');

        const nl = order.newsletter;
        if (nl) {
            document.getElementById('stat-sent').textContent = nl.sent_count;
            document.getElementById('stat-opened').textContent = nl.opened_count;
            document.getElementById('stat-clicked').textContent = nl.clicked_count;
            document.getElementById('stat-failed').textContent = nl.failed_count;
            document.getElementById('stat-unsub').textContent = nl.unsubscribed_count;

            document.getElementById('open-rate-text').textContent = nl.open_rate + '%';
            document.getElementById('open-rate-bar').style.width = Math.min(nl.open_rate, 100) + '%';
            document.getElementById('click-rate-text').textContent = nl.click_rate + '%';
            document.getElementById('click-rate-bar').style.width = Math.min(nl.click_rate, 100) + '%';

            // Newsletter status
            const nlStatusColors = {
                draft: 'bg-gray-100 text-gray-700',
                scheduled: 'bg-blue-100 text-blue-700',
                sending: 'bg-amber-100 text-amber-700',
                sent: 'bg-green-100 text-green-700',
                failed: 'bg-red-100 text-red-700',
                cancelled: 'bg-red-100 text-red-700',
            };
            const nlStatusLabels = {
                draft: 'Draft', scheduled: 'Programat', sending: 'Se trimite',
                sent: 'Trimis', failed: 'Esuat', cancelled: 'Anulat'
            };
            const nlBadge = document.getElementById('newsletter-status-badge');
            nlBadge.textContent = nlStatusLabels[nl.status] || nl.status;
            nlBadge.className = `px-2.5 py-1 text-xs font-medium rounded-full ${nlStatusColors[nl.status] || 'bg-gray-100 text-gray-700'}`;

            let dates = '';
            if (nl.scheduled_at) dates += `Programat: ${fmtDateTime(nl.scheduled_at)}`;
            if (nl.completed_at) dates += (dates ? ' | ' : '') + `Finalizat: ${fmtDateTime(nl.completed_at)}`;
            document.getElementById('newsletter-dates').textContent = dates;
        } else {
            document.getElementById('stat-sent').textContent = order.sent_count || 0;
        }

        // Audience config
        const config = order.config || {};
        const audienceLabels = { own: 'Clientii tai', marketplace: 'Baza Marketplace' };
        document.getElementById('audience-type-label').textContent = audienceLabels[config.audience_type] || config.audience_type || '-';
        document.getElementById('perfect-count').textContent = config.perfect_count ?? config.recipient_count ?? '-';
        document.getElementById('partial-count').textContent = config.partial_count ?? '0';
        document.getElementById('email-template').textContent = config.template || '-';

        // Filters
        const filters = config.filters || {};
        const tags = [];
        if (filters.cities?.length) tags.push(...filters.cities.map(c => `Oras: ${c}`));
        if (filters.categories?.length) tags.push(...filters.categories.map(c => `Categorie: ${c}`));
        if (filters.genres?.length) tags.push(...filters.genres.map(g => `Gen: ${g}`));
        if (filters.gender) tags.push(`Gen: ${filters.gender}`);
        if (filters.age_min || filters.age_max) tags.push(`Varsta: ${filters.age_min || '?'}-${filters.age_max || '?'}`);

        if (tags.length) {
            document.getElementById('filters-used').classList.remove('hidden');
            document.getElementById('filter-tags').innerHTML = tags.map(t =>
                `<span class="px-2.5 py-1 bg-surface text-muted text-xs rounded-full">${t}</span>`
            ).join('');
        }
    }
}

function fmtDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function fmtDateTime(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fmtCurrency(amount) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount || 0);
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
