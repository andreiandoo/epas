<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Biletele mele';
$currentPage = 'tickets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Biletele mele</h1>
                <p class="text-muted text-sm mt-1">Vizualizeaza si descarca biletele tale</p>
            </div>
            <button onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2.5 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Printeaza toate
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 no-print">
            <button onclick="UserTickets.showTab('upcoming')" class="tab-btn active px-4 py-2 rounded-xl text-sm font-medium" data-tab="upcoming">
                Viitoare (<span id="upcoming-count">0</span>)
            </button>
            <button onclick="UserTickets.showTab('past')" class="tab-btn px-4 py-2 rounded-xl text-sm font-medium text-muted bg-surface" data-tab="past">
                Trecute (<span id="past-count">0</span>)
            </button>
        </div>

        <!-- Upcoming Tickets -->
        <div id="tab-upcoming" class="space-y-6">
            <div class="text-center py-8">
                <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
                <p class="text-muted mt-2">Se incarca biletele...</p>
            </div>
        </div>

        <!-- Past Tickets -->
        <div id="tab-past" class="hidden space-y-4"></div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden text-center py-16">
            <div class="w-20 h-20 bg-surface rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-secondary mb-2">Nu ai bilete inca</h3>
            <p class="text-muted mb-6">Descopera evenimente interesante si achizitioneaza primul tau bilet!</p>
            <a href="/" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descopera evenimente
            </a>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const UserTickets = {
    tickets: { upcoming: [], past: [] },

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/login?redirect=/user/tickets';
            return;
        }
        this.loadUserInfo();
        await this.loadTickets();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            document.getElementById('header-user-avatar').innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            document.getElementById('header-user-points').textContent = (user.points || 0).toLocaleString();
        }
    },

    async loadTickets() {
        try {
            const response = await AmbiletAPI.get('/customer/tickets');
            if (response.success) {
                const now = new Date();
                this.tickets.upcoming = response.data.filter(t => new Date(t.event?.date) >= now);
                this.tickets.past = response.data.filter(t => new Date(t.event?.date) < now);
            }
        } catch (error) {
            console.log('Using demo data');
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
        // Load from centralized DEMO_DATA
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.customerTickets) {
            this.tickets.upcoming = DEMO_DATA.customerTickets.upcoming || [];
            this.tickets.past = DEMO_DATA.customerTickets.past || [];
        } else {
            console.warn('DEMO_DATA.customerTickets not found, using fallback');
            this.tickets.upcoming = [];
            this.tickets.past = [];
        }
    },

    renderUpcoming() {
        const container = document.getElementById('tab-upcoming');
        if (this.tickets.upcoming.length === 0) {
            container.innerHTML = '<p class="text-center text-muted py-8">Nu ai bilete pentru evenimente viitoare.</p>';
            return;
        }

        container.innerHTML = this.tickets.upcoming.map(ticket => `
            <div class="ticket-card bg-white rounded-2xl border border-border overflow-hidden">
                <div class="flex flex-col lg:flex-row">
                    <div class="flex-1 p-5 lg:p-6">
                        <div class="flex gap-4">
                            <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-xl overflow-hidden flex-shrink-0">
                                <img src="${ticket.event.image}" class="w-full h-full object-cover" alt="">
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 ${ticket.days_until <= 7 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'} text-xs font-bold rounded">IN ${ticket.days_until} ZILE</span>
                                    <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-semibold rounded">${ticket.event.genre}</span>
                                </div>
                                <h2 class="text-lg lg:text-xl font-bold text-secondary">${ticket.event.title}</h2>
                                <p class="text-sm text-muted mt-1">${ticket.event.subtitle || ''}</p>
                                <div class="mt-4 space-y-1.5">
                                    <div class="flex items-center gap-2 text-sm">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-secondary font-medium">${this.formatDate(ticket.event.date)}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-secondary">Ora ${ticket.event.time} (Deschidere porti: ${ticket.event.doors})</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span class="text-secondary">${ticket.event.venue}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 pt-5 border-t border-border">
                            <p class="text-sm font-semibold text-secondary mb-3">Bilete in aceasta comanda:</p>
                            <div class="flex flex-wrap gap-2">
                                <div class="flex items-center gap-2 px-3 py-2 ${ticket.ticket_type === 'VIP' ? 'bg-primary/5 border-primary/20' : 'bg-surface border-border'} border rounded-lg">
                                    <span class="w-6 h-6 ${ticket.ticket_type === 'VIP' ? 'bg-primary' : 'bg-secondary'} text-white text-xs font-bold rounded flex items-center justify-center">${ticket.quantity}</span>
                                    <span class="text-sm font-medium text-secondary">${ticket.ticket_type}</span>
                                    <span class="text-xs text-muted">x ${ticket.price} lei</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ticket-divider w-full h-4 lg:w-4 lg:h-auto"></div>
                    <div class="bg-surface p-5 lg:p-6 lg:w-72">
                        <p class="text-sm font-semibold text-secondary mb-4 text-center">${ticket.quantity > 1 ? 'Coduri QR pentru intrare' : 'Cod QR pentru intrare'}</p>
                        <div class="space-y-4">
                            ${ticket.tickets.map((t, i) => `
                            <div class="bg-white rounded-xl p-4 border border-border">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <p class="text-xs text-muted">Bilet #${i + 1}</p>
                                        <p class="text-sm font-semibold text-secondary">${t.type}</p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-success/10 text-success text-xs font-bold rounded">VALID</span>
                                </div>
                                <div class="w-full aspect-square bg-white rounded-lg flex items-center justify-center border border-border mb-3">
                                    <svg class="w-32 h-32" viewBox="0 0 100 100">
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
                                <p class="text-xs text-center text-muted font-mono">${t.code}</p>
                            </div>
                            `).join('')}
                        </div>
                        <div class="mt-4 space-y-2 no-print">
                            <button class="w-full btn btn-primary py-2.5 text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Descarca PDF
                            </button>
                            <button class="w-full py-2.5 bg-white text-secondary font-medium rounded-xl text-sm flex items-center justify-center gap-2 hover:bg-primary/10 hover:text-primary transition-colors border border-border">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Adauga in calendar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    renderPast() {
        const container = document.getElementById('tab-past');
        if (this.tickets.past.length === 0) {
            container.innerHTML = '<p class="text-center text-muted py-8">Nu ai bilete pentru evenimente trecute.</p>';
            return;
        }

        container.innerHTML = this.tickets.past.map(ticket => `
            <div class="bg-white rounded-xl border border-border p-4 lg:p-5 opacity-75">
                <div class="flex gap-4">
                    <div class="w-16 h-16 lg:w-20 lg:h-20 rounded-lg overflow-hidden flex-shrink-0 grayscale">
                        <img src="${ticket.event.image}" class="w-full h-full object-cover" alt="">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 bg-muted/20 text-muted text-xs font-bold rounded">INCHEIAT</span>
                        </div>
                        <h3 class="font-semibold text-secondary">${ticket.event.title}</h3>
                        <p class="text-sm text-muted">${this.formatDate(ticket.event.date)} - ${ticket.event.venue}</p>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-xs text-muted">${ticket.quantity}x ${ticket.ticket_type}</span>
                            ${ticket.checked_in ? '<span class="text-xs px-2 py-0.5 bg-success/10 text-success rounded">CHECK-IN OK</span>' : ''}
                        </div>
                    </div>
                    <div class="hidden sm:flex items-center gap-2">
                        <button class="p-2 text-muted hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Descarca">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </button>
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
    }
};

document.addEventListener('DOMContentLoaded', () => UserTickets.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
