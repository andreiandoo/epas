<?php
/**
 * bilete.online — Organizator › Detalii Comandă Serviciu (v3).
 * Route: /organizator/services/{uuid}
 *
 * Single service-order view: order info, payment summary, email-campaign stats
 * (open/click rates, audience breakdown, filters), and tracking-pixel setup
 * (editable Pixel IDs per platform when paid). Ported 1:1 from ambilet to
 * v3 + shell. Activity-centric copy. Wired to organizer.services.orders.show /
 * .tracking-pixels proxy actions via BileteOnlineAPI.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Detalii Comandă Serviciu';
$currentPage = 'services';
$orderUuid   = $_GET['uuid'] ?? '';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Loading -->
        <div id="loading" class="flex items-center justify-center py-20">
            <svg class="h-8 w-8 animate-spin text-vermilion" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- Content (hidden until loaded) -->
        <div id="content" class="hidden">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <nav class="mb-2 flex items-center gap-2 text-sm text-ink-soft">
                        <a href="/organizator/servicii" class="hover:text-vermilion">Servicii Extra</a>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-ink" id="breadcrumb-number"></span>
                    </nav>
                    <h1 class="font-display text-3xl font-bold leading-none" id="page-title">Detalii Comandă</h1>
                </div>
                <a href="/organizator/servicii" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                    Înapoi
                </a>
            </div>

            <!-- Order Info + Status -->
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: Order details -->
                <div class="rounded-2xl border-2 border-ink bg-paper p-6 lg:col-span-2">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl" id="type-icon-bg">
                                <span id="type-icon"></span>
                            </div>
                            <div>
                                <h2 class="font-display text-lg font-bold" id="order-type-label"></h2>
                                <p class="text-sm text-ink-soft" id="order-number-display"></p>
                            </div>
                        </div>
                        <span id="status-badge" class="rounded-full px-3 py-1.5 text-sm font-bold"></span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl bg-paper-2 p-4">
                            <p class="mb-1 text-xs text-ink-soft">Activitate</p>
                            <p class="font-bold text-ink" id="event-name">-</p>
                        </div>
                        <div class="rounded-xl bg-paper-2 p-4">
                            <p class="mb-1 text-xs text-ink-soft">Detalii</p>
                            <p class="font-bold text-ink" id="order-details">-</p>
                        </div>
                        <div class="rounded-xl bg-paper-2 p-4">
                            <p class="mb-1 text-xs text-ink-soft">Creat la</p>
                            <p class="font-bold text-ink" id="created-at">-</p>
                        </div>
                        <div class="rounded-xl bg-paper-2 p-4">
                            <p class="mb-1 text-xs text-ink-soft">Perioadă</p>
                            <p class="font-bold text-ink" id="service-period">-</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Payment -->
                <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                    <h3 class="mb-4 text-sm font-bold text-ink">Plată</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-ink-soft">Subtotal</span>
                            <span class="text-sm font-medium text-ink" id="subtotal">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-ink-soft">TVA</span>
                            <span class="text-sm font-medium text-ink" id="tax">-</span>
                        </div>
                        <div class="flex justify-between border-t-2 border-ink/10 pt-3">
                            <span class="text-sm font-bold text-ink">Total</span>
                            <span class="text-lg font-bold text-vermilion" id="total">-</span>
                        </div>
                        <div class="space-y-2 pt-2 text-sm text-ink-soft">
                            <div class="flex justify-between">
                                <span>Metodă</span>
                                <span id="payment-method">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Status plată</span>
                                <span id="payment-status-text">-</span>
                            </div>
                            <div class="flex justify-between" id="paid-at-row">
                                <span>Plătit la</span>
                                <span id="paid-at">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Campaign Stats (only for email orders) -->
            <div id="email-stats-section" class="mb-6 hidden">
                <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                    <h3 class="mb-6 font-display text-lg font-bold">Statistici Campanie Email</h3>

                    <!-- Stats Cards -->
                    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
                        <div class="rounded-xl bg-sky/10 p-4 text-center">
                            <p class="text-2xl font-bold text-sky" id="stat-sent">0</p>
                            <p class="mt-1 text-xs text-sky/70">Trimise</p>
                        </div>
                        <div class="rounded-xl bg-forest/10 p-4 text-center">
                            <p class="text-2xl font-bold text-forest" id="stat-opened">0</p>
                            <p class="mt-1 text-xs text-forest/70">Deschise</p>
                        </div>
                        <div class="rounded-xl bg-sky/10 p-4 text-center">
                            <p class="text-2xl font-bold text-sky" id="stat-clicked">0</p>
                            <p class="mt-1 text-xs text-sky/70">Click-uri</p>
                        </div>
                        <div class="rounded-xl bg-vermilion/10 p-4 text-center">
                            <p class="text-2xl font-bold text-vermilion" id="stat-failed">0</p>
                            <p class="mt-1 text-xs text-vermilion/70">Eșuate</p>
                        </div>
                        <div class="rounded-xl bg-ochre/10 p-4 text-center">
                            <p class="text-2xl font-bold text-ochre" id="stat-unsub">0</p>
                            <p class="mt-1 text-xs text-ochre/70">Dezabonări</p>
                        </div>
                    </div>

                    <!-- Rates -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-ink">Rata deschidere</span>
                                <span class="text-sm font-bold text-forest" id="open-rate-text">0%</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-ink/10">
                                <div class="h-3 rounded-full bg-forest transition-all" id="open-rate-bar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-ink">Rata click (din deschise)</span>
                                <span class="text-sm font-bold text-sky" id="click-rate-text">0%</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-ink/10">
                                <div class="h-3 rounded-full bg-sky transition-all" id="click-rate-bar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Audience breakdown -->
                    <div class="mt-6 border-t-2 border-ink/10 pt-6">
                        <h4 class="mb-3 text-sm font-bold text-ink">Audiență</h4>
                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" id="audience-breakdown">
                            <div class="rounded-xl bg-paper-2 p-3">
                                <p class="text-xs text-ink-soft">Tip audiență</p>
                                <p class="text-sm font-bold text-ink" id="audience-type-label">-</p>
                            </div>
                            <div class="rounded-xl bg-paper-2 p-3">
                                <p class="text-xs text-ink-soft">Perfect match</p>
                                <p class="text-sm font-bold text-ink" id="perfect-count">-</p>
                            </div>
                            <div class="rounded-xl bg-paper-2 p-3">
                                <p class="text-xs text-ink-soft">Partial match</p>
                                <p class="text-sm font-bold text-ink" id="partial-count">-</p>
                            </div>
                            <div class="rounded-xl bg-paper-2 p-3">
                                <p class="text-xs text-ink-soft">Template</p>
                                <p class="text-sm font-bold capitalize text-ink" id="email-template">-</p>
                            </div>
                        </div>

                        <!-- Filters used -->
                        <div id="filters-used" class="mt-4 hidden">
                            <p class="mb-2 text-xs text-ink-soft">Filtre aplicate</p>
                            <div class="flex flex-wrap gap-2" id="filter-tags"></div>
                        </div>
                    </div>

                    <!-- Newsletter status -->
                    <div class="mt-6 border-t-2 border-ink/10 pt-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-ink-soft">Status campanie:</span>
                                <span id="newsletter-status-badge" class="rounded-full px-2.5 py-1 text-xs font-bold"></span>
                            </div>
                            <div class="text-sm text-ink-soft" id="newsletter-dates"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking Pixel Setup (only for tracking orders) -->
            <div id="tracking-setup-section" class="hidden rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-sky/10 text-sky"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
                    <div>
                        <h2 class="font-bold text-ink">Pixel ID-uri</h2>
                        <p class="text-sm text-ink-soft">Adaugă sau editează ID-urile pixel-urilor pentru platformele cumpărate. Tracking-ul începe să funcționeze automat în momentul în care un Pixel ID este completat.</p>
                    </div>
                </div>
                <div id="tracking-setup-list" class="mt-4 space-y-3"></div>
                <div class="mt-4 flex items-center justify-end gap-3 border-t-2 border-ink/10 pt-4">
                    <span id="tracking-setup-msg" class="text-sm"></span>
                    <button id="tracking-setup-save" type="button" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează ID-uri</button>
                </div>
            </div>

            <!-- Config (collapsible) -->
            <div class="mt-6 rounded-2xl border-2 border-ink bg-paper p-6" id="config-section">
                <button onclick="document.getElementById('config-json').classList.toggle('hidden')" class="flex items-center gap-2 text-sm font-bold text-ink">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Configurație Comandă
                </button>
                <pre id="config-json" class="mt-4 hidden overflow-x-auto rounded-xl bg-paper-2 p-4 text-xs text-ink-soft"></pre>
            </div>
        </div>

        <!-- Error State -->
        <div id="error-state" class="hidden py-20 text-center">
            <svg class="mx-auto mb-4 h-16 w-16 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h2 class="mb-2 font-display text-xl font-bold">Comanda nu a fost găsită</h2>
            <p class="mb-6 text-ink-soft">Verifică link-ul sau întoarce-te la lista de servicii.</p>
            <a href="/organizator/servicii" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Înapoi la Servicii</a>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra  = "<script>\nconst PHP_ORDER_UUID = " . json_encode($orderUuid) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}

const ORDER_UUID = PHP_ORDER_UUID || new URLSearchParams(window.location.search).get('uuid') || window.location.pathname.split('/').pop();

document.addEventListener('DOMContentLoaded', function() {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadOrderDetails();
});

async function loadOrderDetails() {
    try {
        const response = await BileteOnlineAPI.get(`/organizer/services/orders/${ORDER_UUID}`);
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

    document.getElementById('breadcrumb-number').textContent = order.order_number;
    document.getElementById('page-title').textContent = `${order.type_label} - ${order.order_number}`;

    const typeIcons = {
        featuring: '<svg class="w-6 h-6 text-vermilion" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
        email: '<svg class="w-6 h-6 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        tracking: '<svg class="w-6 h-6 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        campaign: '<svg class="w-6 h-6 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>'
    };
    const typeBgColors = { featuring: 'bg-vermilion/10', email: 'bg-forest/10', tracking: 'bg-sky/10', campaign: 'bg-sky/10' };
    document.getElementById('type-icon-bg').className = `w-12 h-12 rounded-xl flex items-center justify-center ${typeBgColors[order.type] || 'bg-paper-2'}`;
    document.getElementById('type-icon').innerHTML = typeIcons[order.type] || '';
    document.getElementById('order-type-label').textContent = order.type_label;
    document.getElementById('order-number-display').textContent = order.order_number;

    const statusColors = {
        active: 'bg-forest/10 text-forest',
        pending_payment: 'bg-ochre/10 text-ochre',
        processing: 'bg-sky/10 text-sky',
        completed: 'bg-ink/10 text-ink-soft',
        cancelled: 'bg-vermilion/10 text-vermilion',
    };
    const badge = document.getElementById('status-badge');
    badge.textContent = order.status_label;
    badge.className = `px-3 py-1.5 text-sm font-bold rounded-full ${statusColors[order.status] || 'bg-ink/10 text-ink-soft'}`;

    document.getElementById('event-name').textContent = order.event?.name || order.event_name || '-';
    document.getElementById('order-details').textContent = order.details || '-';
    document.getElementById('created-at').textContent = fmtDateTime(order.created_at);
    const period = order.service_start_date && order.service_end_date
        ? `${fmtDate(order.service_start_date)} - ${fmtDate(order.service_end_date)}`
        : order.service_start_date ? `Din ${fmtDate(order.service_start_date)}` : '-';
    document.getElementById('service-period').textContent = period;

    document.getElementById('subtotal').textContent = fmtCurrency(order.subtotal) + ' ' + order.currency;
    document.getElementById('tax').textContent = fmtCurrency(order.tax) + ' ' + order.currency;
    document.getElementById('total').textContent = fmtCurrency(order.total) + ' ' + order.currency;
    document.getElementById('payment-method').textContent = order.payment_method === 'card' ? 'Card online' : (order.payment_method === 'transfer' ? 'Transfer bancar' : order.payment_method || '-');
    document.getElementById('payment-status-text').textContent = order.payment_status === 'paid' ? 'Plătit' : (order.payment_status || '-');
    document.getElementById('paid-at').textContent = order.paid_at ? fmtDateTime(order.paid_at) : '-';

    document.getElementById('config-json').textContent = JSON.stringify(order.config, null, 2);

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

            const nlStatusColors = {
                draft: 'bg-ink/10 text-ink-soft',
                scheduled: 'bg-sky/10 text-sky',
                sending: 'bg-ochre/10 text-ochre',
                sent: 'bg-forest/10 text-forest',
                failed: 'bg-vermilion/10 text-vermilion',
                cancelled: 'bg-vermilion/10 text-vermilion',
            };
            const nlStatusLabels = {
                draft: 'Draft', scheduled: 'Programat', sending: 'Se trimite',
                sent: 'Trimis', failed: 'Eșuat', cancelled: 'Anulat'
            };
            const nlBadge = document.getElementById('newsletter-status-badge');
            nlBadge.textContent = nlStatusLabels[nl.status] || nl.status;
            nlBadge.className = `px-2.5 py-1 text-xs font-bold rounded-full ${nlStatusColors[nl.status] || 'bg-ink/10 text-ink-soft'}`;

            let dates = '';
            if (nl.scheduled_at) dates += `Programat: ${fmtDateTime(nl.scheduled_at)}`;
            if (nl.completed_at) dates += (dates ? ' | ' : '') + `Finalizat: ${fmtDateTime(nl.completed_at)}`;
            document.getElementById('newsletter-dates').textContent = dates;
        } else {
            document.getElementById('stat-sent').textContent = order.sent_count || 0;
        }

        const config = order.config || {};
        const audienceLabels = { own: 'Clienții tăi', marketplace: 'Baza Marketplace' };
        document.getElementById('audience-type-label').textContent = audienceLabels[config.audience_type] || config.audience_type || '-';
        document.getElementById('perfect-count').textContent = config.perfect_count ?? config.recipient_count ?? '-';
        document.getElementById('partial-count').textContent = config.partial_count ?? '0';
        document.getElementById('email-template').textContent = config.template || '-';

        const filters = config.filters || {};
        const tags = [];
        if (filters.cities?.length) tags.push(...filters.cities.map(c => `Oraș: ${c}`));
        if (filters.categories?.length) tags.push(...filters.categories.map(c => `Categorie: ${c}`));
        if (filters.genres?.length) tags.push(...filters.genres.map(g => `Gen: ${g}`));
        if (filters.gender) tags.push(`Gen: ${filters.gender}`);
        if (filters.age_min || filters.age_max) tags.push(`Vârstă: ${filters.age_min || '?'}-${filters.age_max || '?'}`);

        if (tags.length) {
            document.getElementById('filters-used').classList.remove('hidden');
            document.getElementById('filter-tags').innerHTML = tags.map(t =>
                `<span class="px-2.5 py-1 bg-paper-2 text-ink-soft text-xs rounded-full">${escHtml(t)}</span>`
            ).join('');
        }
    }

    if (order.type === 'tracking' && order.payment_status === 'paid' && Array.isArray(order.tracking_setup)) {
        renderTrackingSetup(order);
    }
}

const PLATFORM_LABELS = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
const PLATFORM_PLACEHOLDERS = { facebook: '1234567890123456', google: 'AW-XXXXXXXXX', tiktok: 'CXXXXXXXXXXXXXXXXX' };

function renderTrackingSetup(order) {
    const section = document.getElementById('tracking-setup-section');
    const list = document.getElementById('tracking-setup-list');
    if (!section || !list) return;
    section.classList.remove('hidden');
    list.innerHTML = order.tracking_setup.map(t => {
        const filled = !!t.has_pixel;
        const status = filled
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold text-forest bg-forest/10 rounded-full"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Activ</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold text-ochre bg-ochre/10 rounded-full"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Necesită Pixel ID</span>';
        return `
            <div class="border-2 border-ink/15 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-medium text-ink">${PLATFORM_LABELS[t.platform] || t.platform}</p>
                    ${status}
                </div>
                <input type="text" data-tracking-pixel="${escAttr(t.platform)}" value="${escAttr(t.pixel_id || '')}" placeholder="${escAttr(PLATFORM_PLACEHOLDERS[t.platform] || '')}" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" maxlength="50">
            </div>
        `;
    }).join('');

    const saveBtn = document.getElementById('tracking-setup-save');
    saveBtn.onclick = () => saveTrackingPixels(order);
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
}

function escAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

async function saveTrackingPixels(order) {
    const btn = document.getElementById('tracking-setup-save');
    const msg = document.getElementById('tracking-setup-msg');
    const inputs = document.querySelectorAll('[data-tracking-pixel]');
    const pixel_ids = {};
    inputs.forEach(i => { pixel_ids[i.dataset.trackingPixel] = i.value.trim(); });

    btn.disabled = true;
    msg.textContent = 'Se salvează...';
    msg.className = 'text-sm text-ink-soft';
    try {
        const r = await BileteOnlineAPI.post(`/organizer/services/orders/${order.id}/tracking-pixels`, { pixel_ids });
        if (!r.success) throw new Error(r.message || 'Eroare');
        msg.textContent = 'Salvat ' + new Date().toLocaleTimeString('ro-RO');
        msg.className = 'text-sm text-forest';
        if (r.data?.order) renderTrackingSetup(r.data.order);
    } catch (e) {
        msg.textContent = 'Eroare: ' + (e.message || 'încercați din nou');
        msg.className = 'text-sm text-vermilion';
    } finally {
        btn.disabled = false;
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
