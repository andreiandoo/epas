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

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
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
            <a href="/evenimente" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descopera evenimente
            </a>
        </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

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
            // Use getAllTickets to get all tickets (upcoming + past)
            const response = await AmbiletAPI.customer.getAllTickets('all');
            console.log('Tickets response:', response);
            if (response.success && response.data) {
                const tickets = response.data.tickets || response.data || [];
                // API already provides is_upcoming flag
                this.tickets.upcoming = tickets.filter(t => t.event?.is_upcoming === true);
                this.tickets.past = tickets.filter(t => t.event?.is_upcoming === false);
                console.log('Upcoming:', this.tickets.upcoming.length, 'Past:', this.tickets.past.length);
            } else {
                console.log('No data, using demo');
                this.loadDemoData();
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
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

        // Group tickets by event
        const groupedByEvent = {};
        this.tickets.upcoming.forEach(ticket => {
            const eventId = ticket.event?.id || 'unknown';
            if (!groupedByEvent[eventId]) {
                groupedByEvent[eventId] = {
                    event: ticket.event,
                    tickets: []
                };
            }
            groupedByEvent[eventId].tickets.push(ticket);
        });

        const eventGroups = Object.values(groupedByEvent);

        container.innerHTML = eventGroups.map((group, idx) => {
            const event = group.event || {};
            const tickets = group.tickets || [];
            const daysUntil = event.date ? Math.ceil((new Date(event.date) - new Date()) / (1000 * 60 * 60 * 24)) : 0;

            return `
            <div class="overflow-hidden bg-white border ticket-card rounded-2xl border-border">
                <!-- Event Header -->
                <div class="p-5 border-b lg:p-6 border-border">
                    <div class="flex gap-4">
                        ${event.image ? `
                        <div class="flex-shrink-0 w-20 h-20 overflow-hidden lg:w-24 lg:h-24 rounded-xl">
                            <img src="${event.image}" class="object-cover w-full h-full" alt="">
                        </div>` : ''}
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 ${daysUntil <= 7 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'} text-xs font-bold rounded">IN ${daysUntil} ZILE</span>
                                <span class="px-2 py-0.5 bg-surface text-secondary text-xs font-semibold rounded">${tickets.length}x ${tickets[0]?.type || 'Bilet'}</span>
                            </div>
                            <h2 class="text-lg font-bold truncate lg:text-xl text-secondary">${event.name || 'Eveniment'}</h2>
                            <div class="flex flex-wrap mt-3 text-sm gap-x-4 gap-y-1">
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${event.date_formatted || this.formatDateShort(event.date)}
                                </span>
                                ${event.time ? `
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    ${event.time}
                                </span>` : ''}
                                ${event.venue ? `
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    ${event.venue}${event.city ? ', ' + event.city : ''}
                                </span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets Grid -->
                <div class="p-5 lg:p-6 bg-surface/50">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-semibold text-secondary">${tickets.length > 1 ? 'Biletele tale' : 'Biletul tau'}</p>
                        <div class="flex gap-2 no-print">
                            <button onclick="UserTickets.addToCalendar(${idx})" class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-secondary text-xs font-medium rounded-lg border border-border hover:border-primary hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Calendar
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-${Math.min(tickets.length, 4)} gap-3">
                        ${tickets.map((t, i) => `
                        <div class="p-3 text-center bg-white border ticket-qr rounded-xl border-border">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-muted">#${i + 1}</span>
                                <span class="px-1.5 py-0.5 ${t.status === 'valid' ? 'bg-success/10 text-success' : t.status === 'used' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'} text-[10px] font-bold rounded uppercase">${t.status === 'valid' ? 'VALID' : t.status === 'used' ? 'FOLOSIT' : t.status?.toUpperCase()}</span>
                            </div>
                            <div class="w-full aspect-square bg-white rounded-lg flex items-center justify-center border border-border mb-2 mx-auto max-w-[120px]">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(t.code)}&color=181622&margin=0" alt="QR" class="w-full h-full">
                            </div>
                            <p class="text-[10px] text-muted font-mono truncate">${t.code}</p>
                            <p class="mt-1 text-xs font-medium text-secondary">${t.type}</p>
                        </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `}).join('');

        // Store grouped data for calendar function
        this.eventGroups = eventGroups;
    },

    renderPast() {
        const container = document.getElementById('tab-past');
        if (this.tickets.past.length === 0) {
            container.innerHTML = '<p class="py-8 text-center text-muted">Nu ai bilete pentru evenimente trecute.</p>';
            return;
        }

        // Group tickets by event
        const groupedByEvent = {};
        this.tickets.past.forEach(ticket => {
            const eventId = ticket.event?.id || 'unknown';
            if (!groupedByEvent[eventId]) {
                groupedByEvent[eventId] = {
                    event: ticket.event,
                    tickets: []
                };
            }
            groupedByEvent[eventId].tickets.push(ticket);
        });

        const eventGroups = Object.values(groupedByEvent);

        container.innerHTML = eventGroups.map(group => {
            const event = group.event || {};
            const tickets = group.tickets || [];
            const hasCheckedIn = tickets.some(t => t.checked_in);

            return `
            <div class="p-4 bg-white border opacity-75 rounded-xl border-border lg:p-5">
                <div class="flex gap-4">
                    ${event.image ? `
                    <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-lg lg:w-20 lg:h-20 grayscale">
                        <img src="${event.image}" class="object-cover w-full h-full" alt="">
                    </div>` : ''}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 bg-muted/20 text-muted text-xs font-bold rounded">INCHEIAT</span>
                            ${hasCheckedIn ? '<span class="px-2 py-0.5 bg-success/10 text-success text-xs font-bold rounded">CHECK-IN OK</span>' : ''}
                        </div>
                        <h3 class="font-semibold text-secondary">${event.name || 'Eveniment'}</h3>
                        <p class="text-sm text-muted">${event.date_formatted || this.formatDateShort(event.date)}${event.venue ? ' - ' + event.venue : ''}</p>
                        <p class="mt-1 text-xs text-muted">${tickets.length}x ${tickets[0]?.type || 'Bilet'}</p>
                    </div>
                </div>
            </div>
        `}).join('');
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
        const group = this.eventGroups?.[idx];
        if (!group) return;

        const event = group.event;
        const tickets = group.tickets;
        const ticketCode = tickets[0]?.code || 'ticket';

        // Parse date - handle ISO format
        let startDate;
        if (event.date) {
            startDate = new Date(event.date);
            // If time is separate, add it
            if (event.time && !event.date.includes('T')) {
                const [hours, minutes] = event.time.split(':');
                startDate.setHours(parseInt(hours) || 19, parseInt(minutes) || 0);
            }
        } else {
            startDate = new Date();
        }
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
            `SUMMARY:${event.name || 'Eveniment'}`,
            `DESCRIPTION:${tickets.length}x ${tickets[0]?.type || 'Bilet'}`,
            `LOCATION:${event.venue || ''}${event.city ? ', ' + event.city : ''}`,
            'STATUS:CONFIRMED',
            `UID:${ticketCode}@ambilet.ro`,
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\r\n');

        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${(event.name || 'event').replace(/[^a-zA-Z0-9]/g, '_')}.ics`;
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
