<?php
/**
 * Ad Campaign Creation - Order Confirmed
 * Confirmation page showing that AmBilet team has received the campaign request
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
$pageTitle = 'Campanie Confirmata';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';

$orderId = $_GET['order'] ?? null;
$eventId = $_GET['event'] ?? null;

require_once dirname(__DIR__, 2) . '/includes/head.php';
require_once dirname(__DIR__, 2) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__, 2) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="max-w-3xl mx-auto">
                <!-- Success Header -->
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-2xl p-8 text-white mb-8">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold">Comanda Confirmata!</h1>
                            <p class="text-white/80">Echipa AmBilet a preluat cererea ta de campanie publicitara</p>
                        </div>
                    </div>
                </div>

                <!-- Order Timeline -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Ce urmeaza?</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <!-- Step 1 -->
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-success rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="absolute top-10 left-1/2 w-0.5 h-12 bg-success -translate-x-1/2"></div>
                                </div>
                                <div class="flex-1 pb-6">
                                    <h3 class="font-semibold text-secondary">Plata Confirmata</h3>
                                    <p class="text-sm text-muted">Plata a fost procesata cu succes</p>
                                    <p class="text-xs text-success mt-1">Completat</p>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center animate-pulse">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="absolute top-10 left-1/2 w-0.5 h-12 bg-border -translate-x-1/2"></div>
                                </div>
                                <div class="flex-1 pb-6">
                                    <h3 class="font-semibold text-secondary">Analiza & Strategie</h3>
                                    <p class="text-sm text-muted">Echipa noastra analizeaza evenimentul si pregateste strategia campaniei</p>
                                    <p class="text-xs text-primary mt-1">In curs (1-2 zile lucratoare)</p>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-border rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="absolute top-10 left-1/2 w-0.5 h-12 bg-border -translate-x-1/2"></div>
                                </div>
                                <div class="flex-1 pb-6">
                                    <h3 class="font-semibold text-muted">Creare Materiale</h3>
                                    <p class="text-sm text-muted">Design creativ pentru reclame (imagini, video, copy)</p>
                                    <p class="text-xs text-muted mt-1">In asteptare</p>
                                </div>
                            </div>

                            <!-- Step 4 -->
                            <div class="flex gap-4">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-border rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </div>
                                    <div class="absolute top-10 left-1/2 w-0.5 h-12 bg-border -translate-x-1/2"></div>
                                </div>
                                <div class="flex-1 pb-6">
                                    <h3 class="font-semibold text-muted">Aprobare</h3>
                                    <p class="text-sm text-muted">Vei primi materialele pentru aprobare inainte de lansare</p>
                                    <p class="text-xs text-muted mt-1">In asteptare</p>
                                </div>
                            </div>

                            <!-- Step 5 -->
                            <div class="flex gap-4">
                                <div>
                                    <div class="w-10 h-10 bg-border rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-muted">Lansare Campanie</h3>
                                    <p class="text-sm text-muted">Campania va fi lansata si monitorizata de echipa noastra</p>
                                    <p class="text-xs text-muted mt-1">In asteptare</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Detalii Comanda</h2>
                    </div>
                    <div class="p-6" id="order-details">
                        <div class="animate-pulse space-y-4">
                            <div class="h-4 bg-surface rounded w-3/4"></div>
                            <div class="h-4 bg-surface rounded w-1/2"></div>
                        </div>
                    </div>
                </div>

                <!-- Event Preview -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Eveniment</h2>
                    </div>
                    <div class="p-6" id="event-preview">
                        <div class="animate-pulse flex gap-4">
                            <div class="w-24 h-24 bg-surface rounded-xl"></div>
                            <div class="flex-1 space-y-3">
                                <div class="h-5 bg-surface rounded w-3/4"></div>
                                <div class="h-4 bg-surface rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="bg-purple-50 border border-purple-200 rounded-2xl p-6 mb-6">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-purple-900 mb-1">Ai intrebari?</h3>
                            <p class="text-sm text-purple-700 mb-3">Un specialist din echipa noastra te va contacta in urmatoarele 24 de ore pentru a discuta detaliile campaniei.</p>
                            <p class="text-sm text-purple-600">
                                <strong>Email:</strong> marketing@ambilet.ro<br>
                                <strong>Telefon:</strong> 0722 123 456
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <a href="/organizator/services" class="btn btn-secondary flex-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Inapoi la Servicii
                    </a>
                    <a href="/organizator/dashboard" class="btn btn-primary flex-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

const orderId = new URLSearchParams(window.location.search).get('order');
const eventId = new URLSearchParams(window.location.search).get('event');

document.addEventListener('DOMContentLoaded', function() {
    loadOrderDetails();
});

async function loadOrderDetails() {
    try {
        // Load order details
        const orderResponse = await AmbiletAPI.get(`/organizer/services/orders/${orderId}`);
        if (orderResponse.success) {
            renderOrderDetails(orderResponse.data.order);
        }

        // Load event details
        const eventResponse = await AmbiletAPI.get(`/organizer/events/${eventId}`);
        if (eventResponse.success) {
            renderEventPreview(eventResponse.data.event);
        }
    } catch (e) {
        console.error('Error loading order details:', e);
        renderDemoData();
    }
}

function renderOrderDetails(order) {
    const campaignTypes = {
        'basic': 'Campanie Basic (1 platforma)',
        'standard': 'Campanie Standard (2 platforme)',
        'premium': 'Campanie Premium (toate platformele)'
    };

    const details = document.getElementById('order-details');
    details.innerHTML = `
        <div class="space-y-4">
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Numar Comanda:</span>
                <span class="font-medium text-secondary">#${order?.id || orderId}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Pachet:</span>
                <span class="font-medium text-secondary">${campaignTypes[order?.config?.campaign_type] || 'Campanie Standard'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Buget Publicitar:</span>
                <span class="font-medium text-secondary">${AmbiletUtils.formatCurrency(order?.config?.budget || 1000)}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Cost Serviciu:</span>
                <span class="font-medium text-secondary">${AmbiletUtils.formatCurrency(order?.total || 899)}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-muted">Status:</span>
                <span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm font-medium rounded-full">In prelucrare</span>
            </div>
        </div>
    `;
}

function renderEventPreview(event) {
    const preview = document.getElementById('event-preview');
    preview.innerHTML = `
        <div class="flex gap-4">
            <img src="${event?.image || '/assets/images/default-event.png'}" alt="${event?.title || 'Event'}" class="w-24 h-24 rounded-xl object-cover">
            <div class="flex-1">
                <h3 class="font-bold text-secondary text-lg">${event?.title || 'Eveniment'}</h3>
                <p class="text-sm text-muted">${event?.date ? AmbiletUtils.formatDate(event.date) : ''}</p>
                <p class="text-sm text-muted">${event?.venue || ''}</p>
            </div>
        </div>
    `;
}

function renderDemoData() {
    const details = document.getElementById('order-details');
    details.innerHTML = `
        <div class="space-y-4">
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Numar Comanda:</span>
                <span class="font-medium text-secondary">#${orderId || '12345'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Pachet:</span>
                <span class="font-medium text-secondary">Campanie Standard (2 platforme)</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Buget Publicitar:</span>
                <span class="font-medium text-secondary">1,000 RON</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Cost Serviciu:</span>
                <span class="font-medium text-secondary">899 RON</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-muted">Status:</span>
                <span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm font-medium rounded-full">In prelucrare</span>
            </div>
        </div>
    `;

    document.getElementById('event-preview').innerHTML = `
        <div class="flex gap-4">
            <div class="w-24 h-24 rounded-xl bg-surface flex items-center justify-center">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-secondary text-lg">Evenimentul Tau</h3>
                <p class="text-sm text-muted">Data eveniment</p>
                <p class="text-sm text-muted">Locatie</p>
            </div>
        </div>
    `;
}
</script>
JS;
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
