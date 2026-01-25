<?php
/**
 * Email Marketing - Send Campaign
 * Page where organizer can review and send their email campaign after payment
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
$pageTitle = 'Trimite Campania Email';
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
                <div class="bg-gradient-to-r from-success to-green-600 rounded-2xl p-8 text-white mb-8">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold">Plata Confirmata!</h1>
                            <p class="text-white/80">Campania ta de email marketing este gata de trimitere</p>
                        </div>
                    </div>
                </div>

                <!-- Campaign Details Card -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Detalii Campanie</h2>
                    </div>
                    <div class="p-6" id="campaign-details">
                        <div class="animate-pulse space-y-4">
                            <div class="h-4 bg-surface rounded w-3/4"></div>
                            <div class="h-4 bg-surface rounded w-1/2"></div>
                        </div>
                    </div>
                </div>

                <!-- Event Preview -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Eveniment Promovat</h2>
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

                <!-- Email Preview -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border flex items-center justify-between">
                        <h2 class="text-lg font-bold text-secondary">Previzualizare Email</h2>
                        <button onclick="openEmailPreview()" class="text-sm text-primary font-medium hover:underline">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Vezi exemplu complet
                        </button>
                    </div>
                    <div class="p-6 bg-surface">
                        <div class="bg-white rounded-xl border border-border p-6 max-w-lg mx-auto">
                            <div class="text-center mb-4">
                                <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                </div>
                                <p class="text-xs text-muted"><?= SITE_NAME ?></p>
                            </div>
                            <h3 id="email-subject" class="text-lg font-bold text-secondary text-center mb-2">Subiect email...</h3>
                            <p id="email-preview-text" class="text-sm text-muted text-center mb-4">Continut email...</p>
                            <div class="bg-primary text-white text-center py-3 px-6 rounded-xl font-semibold text-sm">
                                Cumpara Bilete
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Send Schedule -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Programare Trimitere</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="send_schedule" value="now" class="w-4 h-4 text-primary" checked>
                                <span class="font-medium text-secondary">Trimite Acum</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="send_schedule" value="scheduled" class="w-4 h-4 text-primary">
                                <span class="font-medium text-secondary">Programeaza</span>
                            </label>
                        </div>
                        <div id="schedule-datetime" class="hidden">
                            <label class="label">Data si Ora Trimitere</label>
                            <input type="datetime-local" id="scheduled-time" class="input w-full max-w-xs">
                            <p class="text-sm text-muted mt-2">Emailurile vor fi trimise automat la data si ora selectata.</p>
                        </div>
                        <div id="send-now-info">
                            <p class="text-sm text-muted">Emailurile vor fi trimise imediat dupa ce apesi butonul de mai jos.</p>
                        </div>
                    </div>
                </div>

                <!-- Summary & Send -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Rezumat Final</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="bg-surface rounded-xl p-4 text-center">
                                <p class="text-3xl font-bold text-primary" id="total-recipients">0</p>
                                <p class="text-sm text-muted">Destinatari</p>
                            </div>
                            <div class="bg-surface rounded-xl p-4 text-center">
                                <p class="text-3xl font-bold text-secondary" id="total-cost">0 RON</p>
                                <p class="text-sm text-muted">Cost (platit)</p>
                            </div>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                            <div class="flex gap-3">
                                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <div>
                                    <p class="font-medium text-amber-800">Important</p>
                                    <p class="text-sm text-amber-700">Dupa trimitere, campania nu mai poate fi anulata sau modificata. Asigura-te ca toate detaliile sunt corecte.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <a href="/organizator/services" class="btn btn-secondary flex-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Inapoi
                            </a>
                            <button id="send-btn" onclick="sendCampaign()" class="btn btn-primary flex-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                Trimite Campania
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Email Preview Modal -->
    <div id="email-preview-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-4 border-b border-border flex items-center justify-between">
                <h3 class="font-bold text-secondary">Previzualizare Email</h3>
                <button onclick="closeEmailPreview()" class="p-2 hover:bg-surface rounded-lg">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6" id="full-email-preview">
                <!-- Full email preview will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="success-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-8 text-center">
            <div class="w-20 h-20 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-2xl font-bold text-secondary mb-2">Campanie Trimisa!</h3>
            <p class="text-muted mb-6" id="success-message">Emailurile sunt in curs de trimitere catre destinatari.</p>
            <a href="/organizator/services" class="btn btn-primary w-full">
                Inapoi la Servicii
            </a>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

const orderId = new URLSearchParams(window.location.search).get('order');
const eventId = new URLSearchParams(window.location.search).get('event');

let campaignData = null;
let eventData = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCampaignDetails();
    setupScheduleToggle();
});

async function loadCampaignDetails() {
    try {
        // Load order/campaign details
        const orderResponse = await AmbiletAPI.get(`/organizer/services/orders/${orderId}`);
        if (orderResponse.success) {
            campaignData = orderResponse.data.order;
            renderCampaignDetails();
        }

        // Load event details
        const eventResponse = await AmbiletAPI.get(`/organizer/events/${eventId}`);
        if (eventResponse.success) {
            eventData = eventResponse.data.event;
            renderEventPreview();
            renderEmailPreview();
        }
    } catch (e) {
        console.error('Error loading campaign details:', e);
        // Show demo data for preview
        renderDemoData();
    }
}

function renderCampaignDetails() {
    const details = document.getElementById('campaign-details');
    const audienceLabels = {
        'all': 'Baza Completa de Utilizatori',
        'filtered': 'Audienta Filtrata (oras/categorie)',
        'own': 'Clientii Tai Anteriori'
    };

    const audience = campaignData?.config?.audience || 'filtered';
    const recipientCount = campaignData?.config?.recipient_count || 45000;
    const cost = campaignData?.total || (recipientCount * 0.05);

    details.innerHTML = `
        <div class="space-y-4">
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Tip Audienta:</span>
                <span class="font-medium text-secondary">${audienceLabels[audience]}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Numar Destinatari:</span>
                <span class="font-medium text-secondary">${AmbiletUtils.formatNumber(recipientCount)}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Cost per Email:</span>
                <span class="font-medium text-secondary">0.05 RON</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-muted">Status Plata:</span>
                <span class="px-3 py-1 bg-success/10 text-success text-sm font-medium rounded-full">Platit</span>
            </div>
        </div>
    `;

    document.getElementById('total-recipients').textContent = AmbiletUtils.formatNumber(recipientCount);
    document.getElementById('total-cost').textContent = AmbiletUtils.formatCurrency(cost);
}

function renderEventPreview() {
    const preview = document.getElementById('event-preview');
    preview.innerHTML = `
        <div class="flex gap-4">
            <img src="${eventData?.image || '/assets/images/default-event.png'}" alt="${eventData?.title || 'Event'}" class="w-24 h-24 rounded-xl object-cover">
            <div class="flex-1">
                <h3 class="font-bold text-secondary text-lg">${eventData?.title || 'Eveniment'}</h3>
                <p class="text-sm text-muted">${eventData?.date ? AmbiletUtils.formatDate(eventData.date) : ''}</p>
                <p class="text-sm text-muted">${eventData?.venue || ''}</p>
                <p class="text-sm text-primary font-medium mt-2">De la ${eventData?.min_price ? AmbiletUtils.formatCurrency(eventData.min_price) : 'N/A'}</p>
            </div>
        </div>
    `;
}

function renderEmailPreview() {
    const subject = `Nu rata: ${eventData?.title || 'Eveniment Special'}`;
    const previewText = `Biletele pentru ${eventData?.title || 'eveniment'} sunt disponibile acum. Rezerva-ti locul inainte sa se epuizeze!`;

    document.getElementById('email-subject').textContent = subject;
    document.getElementById('email-preview-text').textContent = previewText;
}

function renderDemoData() {
    // Demo data for preview when API is not available
    const details = document.getElementById('campaign-details');
    details.innerHTML = `
        <div class="space-y-4">
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Tip Audienta:</span>
                <span class="font-medium text-secondary">Audienta Filtrata</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Numar Destinatari:</span>
                <span class="font-medium text-secondary">~45,000</span>
            </div>
            <div class="flex justify-between py-2 border-b border-border">
                <span class="text-muted">Cost per Email:</span>
                <span class="font-medium text-secondary">0.05 RON</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-muted">Status Plata:</span>
                <span class="px-3 py-1 bg-success/10 text-success text-sm font-medium rounded-full">Platit</span>
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

    document.getElementById('email-subject').textContent = 'Nu rata: Eveniment Special';
    document.getElementById('email-preview-text').textContent = 'Biletele sunt disponibile acum. Rezerva-ti locul!';
    document.getElementById('total-recipients').textContent = '~45,000';
    document.getElementById('total-cost').textContent = '2,250 RON';
}

function setupScheduleToggle() {
    document.querySelectorAll('input[name="send_schedule"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('schedule-datetime').classList.toggle('hidden', this.value !== 'scheduled');
            document.getElementById('send-now-info').classList.toggle('hidden', this.value === 'scheduled');
        });
    });
}

async function sendCampaign() {
    const btn = document.getElementById('send-btn');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `
        <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Se trimite...
    `;

    const schedule = document.querySelector('input[name="send_schedule"]:checked').value;
    const scheduledTime = schedule === 'scheduled' ? document.getElementById('scheduled-time').value : null;

    try {
        const response = await AmbiletAPI.post(`/organizer/services/orders/${orderId}/send-email`, {
            scheduled_time: scheduledTime
        });

        if (response.success) {
            const message = scheduledTime
                ? `Campania a fost programata pentru ${AmbiletUtils.formatDateTime(scheduledTime)}.`
                : 'Emailurile sunt in curs de trimitere catre destinatari.';

            document.getElementById('success-message').textContent = message;
            document.getElementById('success-modal').classList.remove('hidden');
            document.getElementById('success-modal').classList.add('flex');
        } else {
            throw new Error(response.message || 'Eroare la trimiterea campaniei');
        }
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Eroare la trimiterea campaniei. Incearca din nou.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function openEmailPreview() {
    const modal = document.getElementById('email-preview-modal');
    const preview = document.getElementById('full-email-preview');

    preview.innerHTML = `
        <div class="bg-surface p-6 rounded-xl">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-primary rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <h2 class="text-xl font-bold text-secondary">Nu rata: ${eventData?.title || 'Eveniment Special'}</h2>
            </div>

            <div class="bg-white rounded-xl p-4 mb-6">
                <img src="${eventData?.image || '/assets/images/default-event.png'}" alt="Event" class="w-full h-48 object-cover rounded-lg mb-4">
                <h3 class="font-bold text-secondary text-lg mb-2">${eventData?.title || 'Eveniment'}</h3>
                <p class="text-sm text-muted mb-1"><strong>Data:</strong> ${eventData?.date ? AmbiletUtils.formatDate(eventData.date) : 'TBA'}</p>
                <p class="text-sm text-muted mb-4"><strong>Locatie:</strong> ${eventData?.venue || 'TBA'}</p>
                <p class="text-muted mb-4">Biletele pentru acest eveniment sunt disponibile acum! Nu rata ocazia de a participa la unul dintre cele mai asteptate evenimente ale anului.</p>
            </div>

            <div class="text-center">
                <div class="bg-primary text-white py-4 px-8 rounded-xl font-bold text-lg inline-block">
                    Cumpara Bilete Acum
                </div>
            </div>

            <div class="text-center mt-6 pt-6 border-t border-border">
                <p class="text-xs text-muted">Acest email a fost trimis de AmBilet in numele organizatorului.</p>
                <p class="text-xs text-muted">Dezabonare | Politica de Confidentialitate</p>
            </div>
        </div>
    `;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEmailPreview() {
    const modal = document.getElementById('email-preview-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
JS;
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
