<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Biletele mele';
$currentPage = 'tickets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .ticket-qr { transition: transform 0.2s ease; }
    .ticket-qr:hover { transform: scale(1.02); }
    @media print {
        .no-print { display: none !important; }
        .ticket-card { break-inside: avoid; page-break-inside: avoid; }
    }
</style>

<!-- Main Container with Sidebar -->
<div class="px-4 py-6 mx-auto max-w-7xl lg:py-8">
    <div class="flex flex-col gap-6 lg:flex-row">
        <!-- Sidebar -->
        <?php require_once dirname(__DIR__) . '/includes/user-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 lg:pt-24">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Biletele mele</h1>
                <p class="mt-1 text-sm text-muted">Vizualizeaza si descarca biletele tale</p>
            </div>
            <button onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2.5 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Printeaza toate
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 no-print">
            <button onclick="UserTickets.showTab('upcoming')" class="px-4 py-2 text-sm font-medium tab-btn active rounded-xl" data-tab="upcoming">
                Viitoare (<span id="upcoming-count">0</span>)
            </button>
            <button onclick="UserTickets.showTab('past')" class="px-4 py-2 text-sm font-medium tab-btn rounded-xl text-muted bg-surface" data-tab="past">
                Trecute (<span id="past-count">0</span>)
            </button>
        </div>

        <!-- Upcoming Tickets -->
        <div id="tab-upcoming" class="space-y-6">
            <div class="py-8 text-center">
                <div class="w-8 h-8 mx-auto border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <p class="mt-2 text-muted">Se incarca biletele...</p>
            </div>
        </div>

        <!-- Past Tickets -->
        <div id="tab-past" class="hidden space-y-4"></div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-surface rounded-2xl">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai bilete inca</h3>
            <p class="mb-6 text-muted">Descopera evenimente interesante si achizitioneaza primul tau bilet!</p>
            <a href="/" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descopera evenimente
            </a>
        </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const UserTickets = {
    tickets: { upcoming: [], past: [] },

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/bilete';
            return;
        }
        this.loadUserInfo();
        await this.loadTickets();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const avatar = document.getElementById('header-user-avatar');
            if (avatar) avatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            const points = document.getElementById('header-user-points');
            if (points) points.textContent = (user.points || 0).toLocaleString();
        }
    },

    async loadTickets() {
        try {
            const response = await AmbiletAPI.customer.getTickets();
            if (response.success && response.data) {
                const now = new Date();
                const tickets = response.data.tickets || response.data || [];
                this.tickets.upcoming = tickets.filter(t => new Date(t.event?.date) >= now);
                this.tickets.past = tickets.filter(t => new Date(t.event?.date) < now);
            } else {
                this.loadDemoData();
            }
        } catch (error) {
            console.log('Using demo data:', error);
            this.loadDemoData();
        }

        document.getElementById('upcoming-count').textContent = this.tickets.upcoming.length;
        document.getElementById('past-count').textContent = this.tickets.past.length;

        this.renderUpcoming();
        this.renderPast();

        if (this.tickets.upcoming.length === 0 && this.tickets.past.length === 0) {
            document.getElementById('tab-upcoming').classList.add('hidden');
            document.getElementById('empty-state').classList.remove('hidden');
        }
    },

    loadDemoData() {
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.customerTickets) {
            this.tickets.upcoming = DEMO_DATA.customerTickets.upcoming || [];
            this.tickets.past = DEMO_DATA.customerTickets.past || [];
        } else {
            this.tickets.upcoming = [];
            this.tickets.past = [];
        }
    },

    renderUpcoming() {
        const container = document.getElementById('tab-upcoming');
        if (this.tickets.upcoming.length === 0) {
            container.innerHTML = '<p class="py-8 text-center text-muted">Nu ai bilete pentru evenimente viitoare.</p>';
            return;
        }

        container.innerHTML = this.tickets.upcoming.map((ticket, idx) => `
            <div class="overflow-hidden bg-white border ticket-card rounded-2xl border-border">
                <!-- Event Header -->
                <div class="p-5 border-b lg:p-6 border-border">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-20 h-20 overflow-hidden lg:w-24 lg:h-24 rounded-xl">
                            <img src="${ticket.event.image}" class="object-cover w-full h-full" alt="">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 ${ticket.days_until <= 7 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'} text-xs font-bold rounded">IN ${ticket.days_until} ZILE</span>
                                <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-semibold rounded">${ticket.event.genre}</span>
                                <span class="px-2 py-0.5 ${ticket.ticket_type === 'VIP' ? 'bg-accent text-white' : 'bg-surface text-secondary'} text-xs font-semibold rounded">${ticket.quantity}x ${ticket.ticket_type}</span>
                            </div>
                            <h2 class="text-lg font-bold truncate lg:text-xl text-secondary">${ticket.event.title}</h2>
                            <p class="hidden mt-1 text-sm text-muted sm:block">${ticket.event.subtitle || ''}</p>
                            <div class="flex flex-wrap mt-3 text-sm gap-x-4 gap-y-1">
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${this.formatDateShort(ticket.event.date)}
                                </span>
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    ${ticket.event.time}
                                </span>
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    ${ticket.event.venue}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets Grid -->
                <div class="p-5 lg:p-6 bg-surface/50">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-semibold text-secondary">${ticket.quantity > 1 ? 'Biletele tale' : 'Biletul tau'}</p>
                        <div class="flex gap-2 no-print">
                            <button onclick="UserTickets.addToCalendar(${idx})" class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-secondary text-xs font-medium rounded-lg border border-border hover:border-primary hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Calendar
                            </button>
                            <button onclick="UserTickets.downloadPDF(${idx})" class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary-dark transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                PDF
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-${Math.min(ticket.tickets.length, 4)} gap-3">
                        ${ticket.tickets.map((t, i) => `
                        <div class="p-3 text-center bg-white border ticket-qr rounded-xl border-border">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-muted">#${i + 1}</span>
                                <span class="px-1.5 py-0.5 bg-success/10 text-success text-[10px] font-bold rounded">VALID</span>
                            </div>
                            <div class="w-full aspect-square bg-white rounded-lg flex items-center justify-center border border-border mb-2 mx-auto max-w-[120px]">
                                <svg class="w-full h-full p-2" viewBox="0 0 100 100">
                                    <rect width="100" height="100" fill="white"/>
                                    <g fill="#1E293B">
                                        <rect x="10" y="10" width="25" height="25"/>
                                        <rect x="65" y="10" width="25" height="25"/>
                                        <rect x="10" y="65" width="25" height="25"/>
                                        <rect x="15" y="15" width="15" height="15" fill="white"/>
                                        <rect x="70" y="15" width="15" height="15" fill="white"/>
                                        <rect x="15" y="70" width="15" height="15" fill="white"/>
                                        <rect x="18" y="18" width="9" height="9"/>
                                        <rect x="73" y="18" width="9" height="9"/>
                                        <rect x="18" y="73" width="9" height="9"/>
                                        <rect x="40" y="40" width="20" height="20"/>
                                        <rect x="45" y="45" width="10" height="10" fill="white"/>
                                        <rect x="48" y="48" width="4" height="4"/>
                                    </g>
                                </svg>
                            </div>
                            <p class="text-[10px] text-muted font-mono truncate">${t.code}</p>
                            <p class="mt-1 text-xs font-medium text-secondary">${t.type}</p>
                        </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `).join('');
    },

    renderPast() {
        const container = document.getElementById('tab-past');
        if (this.tickets.past.length === 0) {
            container.innerHTML = '<p class="py-8 text-center text-muted">Nu ai bilete pentru evenimente trecute.</p>';
            return;
        }

        container.innerHTML = this.tickets.past.map(ticket => `
            <div class="p-4 bg-white border opacity-75 rounded-xl border-border lg:p-5">
                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-lg lg:w-20 lg:h-20 grayscale">
                        <img src="${ticket.event.image}" class="object-cover w-full h-full" alt="">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 bg-muted/20 text-muted text-xs font-bold rounded">INCHEIAT</span>
                            ${ticket.checked_in ? '<span class="px-2 py-0.5 bg-success/10 text-success text-xs font-bold rounded">CHECK-IN OK</span>' : ''}
                        </div>
                        <h3 class="font-semibold text-secondary">${ticket.event.title}</h3>
                        <p class="text-sm text-muted">${this.formatDateShort(ticket.event.date)} - ${ticket.event.venue}</p>
                        <p class="mt-1 text-xs text-muted">${ticket.quantity}x ${ticket.ticket_type}</p>
                    </div>
                </div>
            </div>
        `).join('');
    },

    showTab(tabName) {
        document.getElementById('tab-upcoming').classList.add('hidden');
        document.getElementById('tab-past').classList.add('hidden');

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('text-muted', 'bg-surface');
        });

        document.getElementById('tab-' + tabName).classList.remove('hidden');
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        document.querySelector(`[data-tab="${tabName}"]`).classList.remove('text-muted', 'bg-surface');
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        const days = ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'];
        const months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        return `${days[date.getDay()]}, ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    },

    formatDateShort(dateStr) {
        const date = new Date(dateStr);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    },

    addToCalendar(idx) {
        const ticket = this.tickets.upcoming[idx];
        if (!ticket) return;

        const event = ticket.event;
        const startDate = new Date(event.date + 'T' + event.time + ':00');
        const endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000); // +3 hours

        const formatICSDate = (d) => {
            return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        };

        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AmBilet//Event Calendar//RO',
            'BEGIN:VEVENT',
            `DTSTART:${formatICSDate(startDate)}`,
            `DTEND:${formatICSDate(endDate)}`,
            `SUMMARY:${event.title}`,
            `DESCRIPTION:${event.subtitle || ''} - ${ticket.quantity}x ${ticket.ticket_type}`,
            `LOCATION:${event.venue}`,
            'STATUS:CONFIRMED',
            `UID:${ticket.code}@ambilet.ro`,
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\r\n');

        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${event.title.replace(/[^a-zA-Z0-9]/g, '_')}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.success('Eveniment descarcat! Deschide fisierul .ics pentru a-l adauga in calendar.');
        }
    },

    downloadPDF(idx) {
        const ticket = this.tickets.upcoming[idx];
        if (!ticket) return;

        // For demo - just print the page
        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.info('Functie PDF in dezvoltare. Foloseste "Printeaza" pentru a salva ca PDF.');
        }
        window.print();
    }
};

document.addEventListener('DOMContentLoaded', () => UserTickets.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
