<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Biletele mele';
$currentPage = 'tickets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .ticket-qr { transition: transform 0.2s ease; cursor: pointer; }
    .ticket-qr:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    @media print {
        .no-print { display: none !important; }
        .ticket-card { break-inside: avoid; page-break-inside: avoid; }
    }
    /* QR Modal styles */
    .qr-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 50; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .qr-modal-backdrop.active { opacity: 1; visibility: visible; }
    .qr-modal { background: white; border-radius: 1.5rem; padding: 2rem; max-width: 90vw; max-height: 90vh; transform: scale(0.9); transition: transform 0.3s ease; text-align: center; }
    .qr-modal-backdrop.active .qr-modal { transform: scale(1); }
    .qr-modal-qr { width: 280px; height: 280px; margin: 1rem auto; background: white; padding: 1rem; border-radius: 1rem; }
    .qr-modal-qr canvas { width: 100% !important; height: 100% !important; }
</style>
<!-- QRCode.js library for local QR generation (with fallback) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js" onerror="window._qrLibFailed=true"></script>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Biletele mele</h1>
                <p class="mt-1 text-sm text-muted">Vizualizează și descarcă biletele tale</p>
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
                <p class="mt-2 text-muted">Se încarcă biletele...</p>
            </div>
        </div>

        <!-- Past Tickets -->
        <div id="tab-past" class="hidden space-y-4"></div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-surface rounded-2xl">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai bilete încă</h3>
            <p class="mb-6 text-muted">Descoperă evenimente interesante și achiziționează primul tău bilet!</p>
            <a href="/evenimente" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descoperă evenimente
            </a>
        </div>

        <!-- QR Modal -->
        <div id="qr-modal-backdrop" class="qr-modal-backdrop no-print" onclick="UserTickets.hideQRModal(event)">
            <div class="qr-modal" onclick="event.stopPropagation()">
                <button onclick="UserTickets.hideQRModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <h3 id="qr-modal-title" class="text-lg font-bold text-secondary mb-1"></h3>
                <p id="qr-modal-attendee" class="text-sm text-muted mb-2"></p>
                <div id="qr-modal-qr" class="qr-modal-qr"></div>
                <p id="qr-modal-code" class="text-sm font-mono text-muted mt-2"></p>
                <p id="qr-modal-type" class="text-sm font-medium text-secondary mt-1"></p>
            </div>
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
    qrCodes: {}, // Cache generated QR codes

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
            if (response.success && response.data) {
                const tickets = response.data.tickets || response.data || [];
                // API already provides is_upcoming flag
                this.tickets.upcoming = tickets.filter(t => t.event?.is_upcoming === true);
                this.tickets.past = tickets.filter(t => t.event?.is_upcoming === false);
                // Sort upcoming by date ASC (closest events first)
                this.tickets.upcoming.sort((a, b) => new Date(a.event?.date) - new Date(b.event?.date));
                // Sort past by date DESC (most recent first)
                this.tickets.past.sort((a, b) => new Date(b.event?.date) - new Date(a.event?.date));
            } else {
                console.warn('No ticket data in API response');
                this.tickets.upcoming = [];
                this.tickets.past = [];
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            this.tickets.upcoming = [];
            this.tickets.past = [];
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


    // Generate QR code using QRCode.js library (with fallback to external API)
    async generateQRCode(code, elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;

        // Check cache first
        if (this.qrCodes[code]) {
            element.innerHTML = '';
            element.appendChild(this.qrCodes[code].cloneNode(true));
            return;
        }

        // Check if QRCode library is available
        if (typeof QRCode !== 'undefined' && QRCode.toCanvas) {
            try {
                const canvas = document.createElement('canvas');
                await QRCode.toCanvas(canvas, code, {
                    width: 120,
                    margin: 0,
                    color: { dark: '#181622', light: '#ffffff' }
                });
                this.qrCodes[code] = canvas;
                element.innerHTML = '';
                element.appendChild(canvas);
                return;
            } catch (error) {
                console.warn('QR canvas generation failed, using API fallback:', error.message);
            }
        }

        // Fallback to external API (always works, no JS library needed)
        const img = document.createElement('img');
        img.src = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(code)}&color=181622&margin=0`;
        img.alt = 'QR';
        img.className = 'w-full h-full';
        img.style.maxWidth = '120px';
        this.qrCodes[code] = img;
        element.innerHTML = '';
        element.appendChild(img);
    },

    // Format time display with doors
    formatTimeDisplay(event) {
        let timeStr = '';
        if (event.time) {
            timeStr = event.time;
            if (event.doors_time) {
                timeStr += ` (Porti: ${event.doors_time})`;
            }
            if (event.end_time) {
                timeStr += ` - ${event.end_time}`;
            }
        } else if (event.doors_time) {
            timeStr = `Porti: ${event.doors_time}`;
        }
        return timeStr;
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
        const self = this;

        container.innerHTML = eventGroups.map((group, idx) => {
            const event = group.event || {};
            const tickets = group.tickets || [];
            const daysUntil = event.date ? Math.ceil((new Date(event.date) - new Date()) / (1000 * 60 * 60 * 24)) : 0;
            const timeDisplay = self.formatTimeDisplay(event);

            return `
            <div class="overflow-hidden bg-white border ticket-card rounded-2xl border-border">
                <!-- Event Header -->
                <div class="p-5 border-b lg:p-6 border-border">
                    <div class="flex gap-4">
                        ${event.image ? `
                        <div class="flex-shrink-0 w-20 h-20 overflow-hidden lg:w-24 lg:h-24 rounded-xl">
                            <img src="${getStorageUrl(event.image)}" class="object-cover w-full h-full" alt="">
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
                                    ${event.date_formatted || self.formatDateShort(event.date)}
                                </span>
                                ${timeDisplay ? `
                                <span class="flex items-center gap-1.5 text-secondary">
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    ${timeDisplay}
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
                        <p class="text-sm font-semibold text-secondary">${tickets.length > 1 ? 'Biletele tale' : 'Biletul tău'}</p>
                        <div class="flex gap-2 no-print">
                            <button onclick="UserTickets.printEventTickets(${idx})" class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-secondary text-xs font-medium rounded-lg border border-border hover:border-primary hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Printează
                            </button>
                            <button onclick="UserTickets.showCalendarOptions(${idx})" class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-secondary text-xs font-medium rounded-lg border border-border hover:border-primary hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Calendar
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-${Math.min(tickets.length, 4)} gap-3">
                        ${tickets.map((t, i) => `
                        <div class="p-3 text-center bg-white border ticket-qr rounded-xl border-border" onclick="UserTickets.showQRModal('${t.code}', '${(t.type || 'Bilet').replace(/'/g, "\\'")}', '${(t.attendee_name || '').replace(/'/g, "\\'")}', '${(event.name || 'Eveniment').replace(/'/g, "\\'")}')">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-muted">#${i + 1}</span>
                                <span class="px-1.5 py-0.5 ${t.status === 'valid' ? 'bg-success/10 text-success' : t.status === 'used' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'} text-[10px] font-bold rounded uppercase">${t.status === 'valid' ? 'VALID' : t.status === 'used' ? 'FOLOSIT' : t.status?.toUpperCase()}</span>
                            </div>
                            <div id="qr-${t.code.replace(/[^a-zA-Z0-9]/g, '')}" class="w-full aspect-square bg-white flex items-center justify-center mb-2 mx-auto max-w-[120px]">
                                <div class="w-6 h-6 border-2 rounded-full animate-spin border-primary border-t-transparent"></div>
                            </div>
                            ${t.attendee_name ? `<p class="text-[10px] text-secondary font-medium truncate">${t.attendee_name}</p>` : ''}
                            <p class="text-[10px] text-muted font-mono truncate">${t.code}</p>
                            <p class="mt-1 text-xs font-medium text-secondary">${t.type}</p>
                            ${t.seat ? `<p class="mt-0.5 text-[10px] text-muted">${[t.seat.section_name, t.seat.row_label ? 'R' + t.seat.row_label : '', t.seat.seat_number ? 'Loc ' + t.seat.seat_number : ''].filter(Boolean).join(', ')}</p>` : ''}
                        </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `}).join('');

        // Store grouped data for calendar function
        this.eventGroups = eventGroups;

        // Generate QR codes after rendering
        setTimeout(() => {
            eventGroups.forEach(group => {
                group.tickets.forEach(t => {
                    const elementId = 'qr-' + t.code.replace(/[^a-zA-Z0-9]/g, '');
                    this.generateQRCode(t.code, elementId);
                });
            });
        }, 100);
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
                        <img src="${getStorageUrl(event.image)}" class="object-cover w-full h-full" alt="">
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

    // Show QR Modal
    async showQRModal(code, type, attendeeName, eventName) {
        const backdrop = document.getElementById('qr-modal-backdrop');
        document.getElementById('qr-modal-title').textContent = eventName;
        document.getElementById('qr-modal-attendee').textContent = attendeeName || '';
        document.getElementById('qr-modal-code').textContent = code;
        document.getElementById('qr-modal-type').textContent = type;

        const qrContainer = document.getElementById('qr-modal-qr');
        qrContainer.innerHTML = '<div class="flex items-center justify-center h-full"><div class="w-8 h-8 border-4 rounded-full animate-spin border-primary border-t-transparent"></div></div>';

        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Generate larger QR code for modal
        if (typeof QRCode !== 'undefined' && QRCode.toCanvas) {
            try {
                const canvas = document.createElement('canvas');
                await QRCode.toCanvas(canvas, code, {
                    width: 280,
                    margin: 1,
                    color: { dark: '#181622', light: '#ffffff' }
                });
                qrContainer.innerHTML = '';
                qrContainer.appendChild(canvas);
                return;
            } catch (error) {
                // Fall through to API fallback
            }
        }
        // Fallback to external API
        qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=${encodeURIComponent(code)}&color=181622&margin=0" alt="QR" style="width:100%;height:100%">`;

    },

    hideQRModal(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('qr-modal-backdrop').classList.remove('active');
        document.body.style.overflow = '';
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        const days = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];
        const months = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        return `${days[date.getDay()]}, ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    },

    formatDateShort(dateStr) {
        const date = new Date(dateStr);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    },

    // Show calendar options (Google Calendar or .ics download)
    showCalendarOptions(idx) {
        const group = this.eventGroups?.[idx];
        if (!group) return;

        const event = group.event;
        const tickets = group.tickets;

        // Create a simple dropdown menu
        const existingMenu = document.getElementById('calendar-dropdown');
        if (existingMenu) existingMenu.remove();

        const menu = document.createElement('div');
        menu.id = 'calendar-dropdown';
        menu.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50';
        menu.onclick = (e) => { if (e.target === menu) menu.remove(); };

        menu.innerHTML = `
            <div class="bg-white rounded-2xl p-4 w-72 shadow-xl">
                <h3 class="text-lg font-bold text-secondary mb-4">Adaugă în calendar</h3>
                <div class="space-y-2">
                    <button onclick="UserTickets.addToGoogleCalendar(${idx}); document.getElementById('calendar-dropdown').remove();" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                        <svg class="w-6 h-6" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        <span class="font-medium text-secondary">Google Calendar</span>
                    </button>
                    <button onclick="UserTickets.addToCalendar(${idx}); document.getElementById('calendar-dropdown').remove();" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="font-medium text-secondary">Descarcă .ics</span>
                    </button>
                </div>
                <button onclick="document.getElementById('calendar-dropdown').remove();" class="mt-4 w-full py-2 text-sm text-muted hover:text-secondary transition-colors">Anulează</button>
            </div>
        `;

        document.body.appendChild(menu);
    },

    // Add to Google Calendar
    addToGoogleCalendar(idx) {
        const group = this.eventGroups?.[idx];
        if (!group) return;

        const event = group.event;
        const tickets = group.tickets;

        // Parse date
        let startDate;
        if (event.date) {
            startDate = new Date(event.date);
            if (event.time && !event.date.includes('T')) {
                const [hours, minutes] = event.time.split(':');
                startDate.setHours(parseInt(hours) || 19, parseInt(minutes) || 0);
            }
        } else {
            startDate = new Date();
        }

        // Calculate end time (use end_time if available, otherwise +3 hours)
        let endDate;
        if (event.end_time) {
            endDate = new Date(startDate);
            const [hours, minutes] = event.end_time.split(':');
            endDate.setHours(parseInt(hours), parseInt(minutes) || 0);
            // If end time is before start time, it's next day
            if (endDate < startDate) {
                endDate.setDate(endDate.getDate() + 1);
            }
        } else {
            endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000);
        }

        // Format for Google Calendar
        const formatGoogleDate = (d) => {
            return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        };

        const googleUrl = new URL('https://calendar.google.com/calendar/render');
        googleUrl.searchParams.set('action', 'TEMPLATE');
        googleUrl.searchParams.set('text', event.name || 'Eveniment');
        googleUrl.searchParams.set('dates', `${formatGoogleDate(startDate)}/${formatGoogleDate(endDate)}`);
        googleUrl.searchParams.set('details', `${tickets.length}x ${tickets[0]?.type || 'Bilet'}\n\nBilete achizitionate de pe AmBilet.ro`);
        googleUrl.searchParams.set('location', `${event.venue || ''}${event.city ? ', ' + event.city : ''}`);

        window.open(googleUrl.toString(), '_blank');

        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.success('Se deschide Google Calendar...');
        }
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

        // Calculate end time
        let endDate;
        if (event.end_time) {
            endDate = new Date(startDate);
            const [hours, minutes] = event.end_time.split(':');
            endDate.setHours(parseInt(hours), parseInt(minutes) || 0);
            if (endDate < startDate) {
                endDate.setDate(endDate.getDate() + 1);
            }
        } else {
            endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000);
        }

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
            AmbiletNotifications.success('Eveniment descărcat! Deschide fișierul .ics pentru a-l adăuga în calendar.');
        }
    },

    /**
     * Print tickets for a specific event group (1 ticket per A4 page)
     */
    async printEventTickets(idx) {
        const group = this.eventGroups?.[idx];
        if (!group) return;

        const event = group.event || {};
        const tickets = group.tickets || [];
        const timeDisplay = this.formatTimeDisplay(event);

        // Generate QR code data URLs for print
        const qrDataUrls = {};
        for (const t of tickets) {
            if (typeof QRCode !== 'undefined' && QRCode.toCanvas) {
                try {
                    const canvas = document.createElement('canvas');
                    await QRCode.toCanvas(canvas, t.code, {
                        width: 200,
                        margin: 1,
                        color: { dark: '#181622', light: '#ffffff' }
                    });
                    qrDataUrls[t.code] = canvas.toDataURL('image/png');
                    continue;
                } catch (e) {
                    // Fall through to API fallback
                }
            }
            qrDataUrls[t.code] = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(t.code)}&color=181622&margin=0`;
        }

        // Build print HTML - 1 ticket per A4 page
        const printHtml = `<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>Bilete - ${event.name || 'Eveniment'}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a2e; }
    @page { size: A4; margin: 15mm; }
    .ticket-page { page-break-after: always; height: 100vh; display: flex; flex-direction: column; justify-content: space-between; padding: 20px 0; }
    .ticket-page:last-child { page-break-after: avoid; }
    .ticket-header { text-align: center; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; }
    .event-name { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
    .event-details { display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; color: #4b5563; font-size: 14px; margin-top: 12px; }
    .event-details span { display: flex; align-items: center; gap: 6px; }
    .ticket-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
    .qr-section { text-align: center; }
    .qr-section img { width: 200px; height: 200px; }
    .ticket-code { font-family: monospace; font-size: 18px; font-weight: 700; margin-top: 8px; letter-spacing: 1px; }
    .ticket-info { text-align: center; }
    .ticket-type { font-size: 18px; font-weight: 600; color: #1a1a2e; }
    .ticket-seat { font-size: 14px; color: #4b5563; margin-top: 4px; }
    .ticket-attendee { font-size: 14px; color: #6b7280; margin-top: 4px; }
    .ticket-number { font-size: 12px; color: #9ca3af; }
    .ticket-footer { text-align: center; border-top: 1px solid #e5e7eb; padding-top: 16px; font-size: 11px; color: #9ca3af; }
</style>
</head><body>
${tickets.map((t, i) => {
    const seatInfo = t.seat ? [t.seat.section_name, t.seat.row_label ? 'Rând ' + t.seat.row_label : '', t.seat.seat_number ? 'Loc ' + t.seat.seat_number : ''].filter(Boolean).join(', ') : '';
    return `
    <div class="ticket-page">
        <div class="ticket-header">
            <div class="event-name">${event.name || 'Eveniment'}</div>
            <div class="event-details">
                <span>📅 ${event.date_formatted || this.formatDateShort(event.date)}</span>
                ${timeDisplay ? `<span>🕐 ${timeDisplay}</span>` : ''}
                ${event.venue ? `<span>📍 ${event.venue}${event.city ? ', ' + event.city : ''}</span>` : ''}
            </div>
        </div>
        <div class="ticket-body">
            <div class="ticket-number">Bilet ${i + 1} din ${tickets.length}</div>
            <div class="qr-section">
                <img src="${qrDataUrls[t.code] || ''}" alt="QR Code">
                <div class="ticket-code">${t.code}</div>
            </div>
            <div class="ticket-info">
                <div class="ticket-type">${t.type || 'Bilet Standard'}</div>
                ${seatInfo ? `<div class="ticket-seat">${seatInfo}</div>` : ''}
                ${t.attendee_name ? `<div class="ticket-attendee">${t.attendee_name}</div>` : ''}
            </div>
        </div>
        <div class="ticket-footer">
            Prezintă acest cod QR la intrare • ${event.venue ? event.venue : ''}${event.city ? ', ' + event.city : ''}
        </div>
    </div>`;
}).join('')}
</body></html>`;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(printHtml);
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.print();
        };
    },

    downloadPDF(idx) {
        const ticket = this.tickets.upcoming[idx];
        if (!ticket) return;

        // For demo - just print the page
        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.info('Funcție PDF în dezvoltare. Folosește "Printează" pentru a salva ca PDF.');
        }
        window.print();
    }
};

// Close QR modal with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') UserTickets.hideQRModal();
});

document.addEventListener('DOMContentLoaded', () => UserTickets.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
