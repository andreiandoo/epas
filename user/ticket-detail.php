<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Detalii Bilet';
$currentPage = 'tickets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Container -->
<div class="px-4 py-6 mx-auto max-w-4xl lg:py-8">
    <!-- Back Link -->
    <a href="/cont/bilete" class="inline-flex items-center gap-2 mb-6 text-sm font-medium text-muted hover:text-primary">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Inapoi la bilete
    </a>

    <!-- Ticket Card -->
    <div class="overflow-hidden bg-white shadow-lg rounded-2xl" id="ticket-card">
        <!-- Ticket Header -->
        <div class="relative p-6 text-white bg-gradient-to-br from-secondary to-gray-900 lg:p-8">
            <div class="absolute top-0 right-0 w-1/2 h-full opacity-20 bg-gradient-radial from-primary/50 to-transparent"></div>
            <div class="relative z-10">
                <span class="inline-flex items-center gap-2 px-4 py-2 mb-4 text-xs font-bold tracking-wider uppercase rounded-full bg-gradient-to-r from-primary to-primary/80" id="ticket-badge">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <span id="ticket-type">---</span>
                </span>
                <h1 class="mb-2 text-2xl font-extrabold lg:text-3xl" id="event-title">---</h1>
                <p class="text-gray-400" id="event-subtitle">---</p>
            </div>
        </div>

        <!-- Ticket Body -->
        <div class="flex flex-col lg:flex-row">
            <!-- Event Details -->
            <div class="relative flex-1 p-6 border-b lg:border-b-0 lg:border-r border-dashed border-border lg:p-8">
                <!-- Cutout circles (hidden on mobile) -->
                <div class="absolute hidden w-7 h-7 bg-surface rounded-full -right-3.5 -top-3.5 lg:block"></div>
                <div class="absolute hidden w-7 h-7 bg-surface rounded-full -right-3.5 -bottom-3.5 lg:block"></div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-muted">Data & Ora</div>
                        <div class="mt-1 font-semibold text-secondary" id="event-date">---</div>
                        <div class="text-sm text-muted" id="event-time">---</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-muted">Locatie</div>
                        <div class="mt-1 font-semibold text-secondary" id="event-venue">---</div>
                        <div class="text-sm text-muted" id="event-address">---</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-muted">Tip bilet</div>
                        <div class="mt-1 text-lg font-semibold text-primary" id="ticket-type-detail">---</div>
                        <div class="text-sm text-muted" id="ticket-type-desc">---</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wider uppercase text-muted">Pret</div>
                        <div class="mt-1 font-semibold text-secondary" id="ticket-price">---</div>
                        <div class="text-sm text-muted">Taxe incluse</div>
                    </div>
                </div>

                <!-- Attendee -->
                <div class="pt-6 mt-6 border-t border-border">
                    <h3 class="mb-4 text-sm font-semibold text-secondary">Detinator bilet</h3>
                    <div class="flex items-center gap-4 p-4 rounded-xl bg-surface">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-lg font-bold text-white rounded-full bg-gradient-to-br from-primary to-primary/80" id="attendee-avatar">--</div>
                        <div>
                            <h4 class="font-semibold text-secondary" id="attendee-name">---</h4>
                            <p class="text-sm text-muted" id="attendee-email">---</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Section -->
            <div class="flex flex-col items-center justify-center p-6 lg:p-8 lg:w-80 bg-surface/50">
                <div class="p-4 mb-4 bg-white shadow-lg rounded-xl">
                    <div class="flex items-center justify-center w-48 h-48" id="qr-code">
                        <!-- QR Code will be loaded here -->
                        <svg class="w-44 h-44" viewBox="0 0 100 100" fill="none">
                            <rect width="100" height="100" fill="white"/>
                            <rect x="5" y="5" width="25" height="25" fill="#1E293B"/>
                            <rect x="8" y="8" width="19" height="19" fill="white"/>
                            <rect x="11" y="11" width="13" height="13" fill="#1E293B"/>
                            <rect x="70" y="5" width="25" height="25" fill="#1E293B"/>
                            <rect x="73" y="8" width="19" height="19" fill="white"/>
                            <rect x="76" y="11" width="13" height="13" fill="#1E293B"/>
                            <rect x="5" y="70" width="25" height="25" fill="#1E293B"/>
                            <rect x="8" y="73" width="19" height="19" fill="white"/>
                            <rect x="11" y="76" width="13" height="13" fill="#1E293B"/>
                            <rect x="35" y="5" width="5" height="5" fill="#1E293B"/>
                            <rect x="45" y="5" width="5" height="5" fill="#1E293B"/>
                            <rect x="55" y="10" width="5" height="5" fill="#1E293B"/>
                            <rect x="35" y="35" width="5" height="5" fill="#1E293B"/>
                            <rect x="45" y="45" width="10" height="10" rx="2" fill="#A51C30"/>
                            <rect x="70" y="70" width="5" height="5" fill="#1E293B"/>
                            <rect x="80" y="75" width="5" height="5" fill="#1E293B"/>
                        </svg>
                    </div>
                </div>
                <div class="mb-1 font-mono text-lg font-bold tracking-wider text-secondary" id="ticket-code">---</div>
                <div class="mb-4 text-xs tracking-wider uppercase text-muted">Cod bilet</div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold bg-white border rounded-lg border-border text-muted hover:border-primary hover:text-primary transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print
                    </button>
                    <button onclick="TicketDetailPage.downloadTicket()" class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold bg-white border rounded-lg border-border text-muted hover:border-primary hover:text-primary transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Salveaza
                    </button>
                </div>
            </div>
        </div>

        <!-- Ticket Footer -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-surface border-t border-border lg:px-8">
            <div class="flex flex-wrap items-center gap-4 text-sm text-muted">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Comanda: <strong class="text-secondary" id="order-number">---</strong>
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Cumparat: <strong class="text-secondary" id="purchase-date">---</strong>
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    Bilet <strong class="text-secondary" id="ticket-index">---</strong>
                </span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col gap-3 mt-6 sm:flex-row">
        <button onclick="window.print()" class="flex items-center justify-center flex-1 gap-2 btn btn-primary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Printeaza biletul
        </button>
        <button onclick="TicketDetailPage.downloadTicket()" class="flex items-center justify-center flex-1 gap-2 btn btn-secondary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Descarca PDF
        </button>
        <button onclick="TicketDetailPage.addToWallet()" class="flex items-center justify-center flex-1 gap-2 btn btn-secondary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Adauga in Wallet
        </button>
    </div>

    <!-- Important Notice -->
    <div class="flex items-start gap-3 p-4 mt-6 border rounded-xl bg-yellow-50 border-yellow-400">
        <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <p class="text-sm text-yellow-800"><strong>Important:</strong> Prezinta acest cod QR la intrare. Biletul poate fi scanat o singura data. Te rugam sa ai la tine un act de identitate valid.</p>
    </div>

    <!-- Transfer Section -->
    <div class="p-6 mt-6 bg-white border rounded-xl border-border" id="transfer-section">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-secondary">Transfer bilet</h3>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700" id="transfer-status">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Transferabil
            </span>
        </div>
        <p class="mb-4 text-sm text-muted">Poti transfera acest bilet altei persoane. Biletul va fi dezactivat in contul tau si activat in contul destinatarului.</p>
        <button onclick="TicketDetailPage.transferTicket()" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg bg-surface text-gray-600 hover:bg-border hover:text-secondary transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Transfera biletul
        </button>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const TicketDetailPage = {
    ticketId: null,

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=' + window.location.pathname;
            return;
        }

        this.ticketId = this.getTicketIdFromUrl();
        if (this.ticketId) {
            this.loadTicket();
        } else {
            AmbiletNotifications.error('Biletul nu a fost gasit.');
            window.location.href = '/cont/bilete';
        }
    },

    getTicketIdFromUrl() {
        const pathParts = window.location.pathname.split('/');
        return pathParts[pathParts.length - 1] || null;
    },

    async loadTicket() {
        try {
            const response = await AmbiletAPI.get('/customer/tickets/' + this.ticketId);
            if (response.success && response.ticket) {
                this.renderTicket(response.ticket);
            } else {
                AmbiletNotifications.error('Biletul nu a fost gasit.');
                window.location.href = '/cont/bilete';
            }
        } catch (error) {
            console.error('Error loading ticket:', error);
            // Demo data for testing
            this.renderTicket({
                id: this.ticketId,
                code: 'AMB-GC-2024-00847-01',
                type: 'Golden Circle VIP',
                type_description: 'Acces in primele randuri',
                price: '600,00 RON',
                event_title: 'Coldplay - Music of the Spheres World Tour',
                event_subtitle: 'Experienta VIP cu acces exclusiv in zona Golden Circle',
                event_date: 'Sambata, 15 Iunie 2025',
                event_time: 'Ora 20:00 (Porti: 18:00)',
                venue: 'Arena Nationala',
                address: 'Bd. Basarabia 37-39, Bucuresti',
                attendee_name: 'Andrei Popescu',
                attendee_email: 'andrei.popescu@email.com',
                order_number: '#AMB-2024-00847',
                purchase_date: '15 Dec 2024',
                ticket_index: '1 din 2',
                transferable: true
            });
        }
    },

    renderTicket(ticket) {
        document.getElementById('ticket-badge').querySelector('span:last-child').textContent = ticket.type;
        document.getElementById('ticket-type').textContent = ticket.type;
        document.getElementById('event-title').textContent = ticket.event_title;
        document.getElementById('event-subtitle').textContent = ticket.event_subtitle || '';
        document.getElementById('event-date').textContent = ticket.event_date;
        document.getElementById('event-time').textContent = ticket.event_time;
        document.getElementById('event-venue').textContent = ticket.venue;
        document.getElementById('event-address').textContent = ticket.address;
        document.getElementById('ticket-type-detail').textContent = ticket.type;
        document.getElementById('ticket-type-desc').textContent = ticket.type_description || '';
        document.getElementById('ticket-price').textContent = ticket.price;
        document.getElementById('ticket-code').textContent = ticket.code;
        document.getElementById('order-number').textContent = ticket.order_number;
        document.getElementById('purchase-date').textContent = ticket.purchase_date;
        document.getElementById('ticket-index').textContent = ticket.ticket_index;

        // Attendee
        const initials = ticket.attendee_name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || '--';
        document.getElementById('attendee-avatar').textContent = initials;
        document.getElementById('attendee-name').textContent = ticket.attendee_name;
        document.getElementById('attendee-email').textContent = ticket.attendee_email;

        // Transfer status
        if (!ticket.transferable) {
            const transferStatus = document.getElementById('transfer-status');
            transferStatus.textContent = 'Nu este transferabil';
            transferStatus.className = 'inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600';
            document.getElementById('transfer-section').querySelector('button').disabled = true;
            document.getElementById('transfer-section').querySelector('button').classList.add('opacity-50', 'cursor-not-allowed');
        }
    },

    downloadTicket() {
        AmbiletNotifications.info('Descarcarea PDF va incepe in curand...');
    },

    addToWallet() {
        AmbiletNotifications.info('Functie in dezvoltare.');
    },

    transferTicket() {
        AmbiletNotifications.info('Functie in dezvoltare. Vei putea transfera biletul in curand.');
    }
};

document.addEventListener('DOMContentLoaded', () => TicketDetailPage.init());
</script>

<style>
@media print {
    .btn, .notice-box, #transfer-section, header, footer, nav, .back-link { display: none !important; }
    body { background: white !important; }
    #ticket-card { box-shadow: none !important; border: 2px solid #E2E8F0 !important; }
}
</style>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
