<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Dashboard Organizator';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'dashboard';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    
        <main class="flex-1 p-4 lg:p-8">
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-primary to-primary-dark rounded-2xl p-6 mb-8 text-white relative overflow-hidden">
                <div class="absolute right-0 top-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute right-20 bottom-0 w-32 h-32 bg-white/5 rounded-full translate-y-1/2"></div>
                <div class="relative">
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Bun venit inapoi! 👋</h1>
                    <p id="welcome-stat" class="text-white/80 mb-4">Ai vandut 0 bilete in ultima saptamana. Continua tot asa!</p>
                    <a href="/organizer/events?new=1" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-primary rounded-xl font-semibold hover:bg-white/90 transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Creeaza eveniment nou
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="stat-card bg-white rounded-2xl p-5 border border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span id="stat-revenue-change" class="text-xs font-medium text-success bg-success/10 px-2 py-1 rounded-full">+0%</span>
                    </div>
                    <p id="stat-revenue" class="text-2xl font-bold text-secondary">0 lei</p>
                    <p class="text-sm text-muted mt-1">Venituri luna aceasta</p>
                </div>

                <div class="stat-card bg-white rounded-2xl p-5 border border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <span id="stat-tickets-change" class="text-xs font-medium text-success bg-success/10 px-2 py-1 rounded-full">+0%</span>
                    </div>
                    <p id="stat-tickets" class="text-2xl font-bold text-secondary">0</p>
                    <p class="text-sm text-muted mt-1">Bilete vandute</p>
                </div>

                <div class="stat-card bg-white rounded-2xl p-5 border border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </div>
                    <p id="stat-events" class="text-2xl font-bold text-secondary">0</p>
                    <p class="text-sm text-muted mt-1">Evenimente active</p>
                </div>

                <div class="stat-card bg-white rounded-2xl p-5 border border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span id="stat-conv-change" class="text-xs font-medium text-success bg-success/10 px-2 py-1 rounded-full">+0%</span>
                    </div>
                    <p id="stat-conversion" class="text-2xl font-bold text-secondary">0%</p>
                    <p class="text-sm text-muted mt-1">Rata conversie</p>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Sales Chart -->
                    <div class="bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Vanzari bilete</h2>
                                <p class="text-sm text-muted">Ultimele 7 zile</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="chart-period px-3 py-1.5 text-xs font-medium bg-primary/10 text-primary rounded-lg" data-days="7">7 zile</button>
                                <button class="chart-period px-3 py-1.5 text-xs font-medium text-muted hover:bg-surface rounded-lg transition-colors" data-days="30">30 zile</button>
                                <button class="chart-period px-3 py-1.5 text-xs font-medium text-muted hover:bg-surface rounded-lg transition-colors" data-days="90">90 zile</button>
                            </div>
                        </div>
                        <div class="h-64">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Events Table -->
                    <div class="bg-white rounded-2xl border border-border overflow-hidden">
                        <div class="flex items-center justify-between p-6 border-b border-border">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Evenimentele tale</h2>
                                <p id="events-count-text" class="text-sm text-muted">0 evenimente active</p>
                            </div>
                            <a href="/organizer/events" class="text-sm text-primary font-medium">Vezi toate &rarr;</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full" id="events-table">
                                <thead class="bg-surface">
                                    <tr>
                                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-6 py-3">Eveniment</th>
                                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-6 py-3">Data</th>
                                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-6 py-3">Vanzari</th>
                                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-6 py-3">Status</th>
                                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-6 py-3">Actiuni</th>
                                    </tr>
                                </thead>
                                <tbody id="events-tbody" class="divide-y divide-border"></tbody>
                            </table>
                        </div>
                        <div id="no-events" class="hidden text-center py-12">
                            <svg class="w-12 h-12 text-muted/30 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-muted mb-3">Nu ai evenimente inca</p>
                            <a href="/organizer/events?new=1" class="btn-primary px-4 py-2 rounded-xl text-white text-sm font-medium">Creeaza primul eveniment</a>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- Upcoming Event -->
                    <div class="bg-white rounded-2xl border border-border overflow-hidden" id="upcoming-event-card">
                        <div class="p-5 border-b border-border">
                            <h2 class="font-bold text-secondary">Urmatorul eveniment</h2>
                        </div>
                        <div id="upcoming-event-content" class="p-5"></div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl border border-border p-5">
                        <h2 class="font-bold text-secondary mb-4">Actiuni rapide</h2>
                        <div class="space-y-2">
                            <a href="/organizer/events?new=1" class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-secondary text-sm">Creeaza eveniment</p>
                                    <p class="text-xs text-muted">Adauga un eveniment nou</p>
                                </div>
                            </a>
                            <a href="/organizer/reports" class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                                <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-secondary text-sm">Descarca raport</p>
                                    <p class="text-xs text-muted">Export vanzari si taxe</p>
                                </div>
                            </a>
                            <a href="/organizer/promo" class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                                <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-secondary text-sm">Cod promotional</p>
                                    <p class="text-xs text-muted">Creeaza reduceri</p>
                                </div>
                            </a>
                            <a href="/organizer/email" class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface transition-colors">
                                <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-secondary text-sm">Trimite email</p>
                                    <p class="text-xs text-muted">Notifica participantii</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Tax Summary -->
                    <div class="bg-white rounded-2xl border border-border p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-bold text-secondary">Taxe automatizate</h2>
                            <span class="px-2 py-1 bg-success/10 text-success text-xs font-semibold rounded-full">Conform</span>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b border-border">
                                <span class="text-sm text-muted">Timbru Muzical (5%)</span>
                                <span id="tax-musical" class="font-medium text-secondary">0 lei</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-border">
                                <span class="text-sm text-muted">Taxa Crucea Rosie (1%)</span>
                                <span id="tax-redcross" class="font-medium text-secondary">0 lei</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-sm font-medium text-secondary">Total retinut</span>
                                <span id="tax-total" class="font-bold text-primary">0 lei</span>
                            </div>
                        </div>
                        <p class="text-xs text-muted mt-4 flex items-center gap-1">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Toate taxele sunt platite automat
                        </p>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-2xl border border-border p-5">
                        <h2 class="font-bold text-secondary mb-4">Activitate recenta</h2>
                        <div id="recent-activity" class="space-y-4"></div>
                        <a href="/organizer/activity" class="block text-center text-sm text-primary font-medium mt-4">Vezi toata activitatea</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
const OrgDashboard = {
    org: null,
    data: null,
    salesChart: null,

    async init() {
        AmbiletAuth.requireOrganizerAuth();
        this.org = AmbiletAuth.getOrganizer();
        this.renderOrgInfo();
        await this.loadDashboard();
        this.initChart();
        this.setupChartPeriod();
    },

    renderOrgInfo() {
        if (!this.org) return;
        const name = this.org.company_name || `${this.org.first_name} ${this.org.last_name}`;
        const initials = this.getInitials(this.org.first_name, this.org.last_name);

        document.getElementById('sidebar-initials').textContent = initials;
        document.getElementById('sidebar-name').textContent = name;
        document.getElementById('header-initials').textContent = initials;
        document.getElementById('dropdown-name').textContent = name;
        document.getElementById('dropdown-email').textContent = this.org.email || '';
    },

    async loadDashboard() {
        try {
            const response = await AmbiletAPI.get('/organizer/dashboard');
            if (response.success && response.data) {
                this.data = response.data;
                this.render();
                return;
            }
        } catch (e) {}

        // Demo data
        this.data = {
            revenue_month: 45230,
            revenue_change: 23,
            tickets_sold: 892,
            tickets_change: 15,
            active_events: 5,
            conversion_rate: 4.2,
            conversion_change: 5,
            weekly_sales: 127,
            events: [
                { id: 1, title: 'Mos Craciun e Rocker', artist: 'Dirty Shirt', venue: 'Grand Gala', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=100', start_date: '2024-12-27', tickets_sold: 234, tickets_total: 300, status: 'active' },
                { id: 2, title: 'Cargo Live', artist: 'Cargo', venue: 'Arenele Romane', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=100', start_date: '2025-01-15', tickets_sold: 450, tickets_total: 1000, status: 'active' },
                { id: 3, title: 'Trooper Unplugged', artist: 'Trooper', venue: 'Hard Rock Cafe', image: 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=100', start_date: '2025-01-22', tickets_sold: 50, tickets_total: 200, status: 'active' }
            ],
            sales_chart: {
                labels: ['Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sam', 'Dum'],
                tickets: [12, 19, 8, 25, 32, 45, 28],
                revenue: [960, 1520, 640, 2000, 2560, 3600, 2240]
            },
            recent_activity: [
                { type: 'sale', message: 'Vanzare: 3x VIP', details: 'Mos Craciun e Rocker - 450 lei', time: 'Acum 15 min' },
                { type: 'sale', message: 'Vanzare: 2x Standard', details: 'Cargo Live - 160 lei', time: 'Acum 45 min' },
                { type: 'promo', message: 'Cod ROCK2024 folosit', details: 'Reducere 10% aplicata', time: 'Acum 1 ora' }
            ],
            taxes: { musical: 2261, redcross: 452, total: 2713 }
        };
        this.render();
    },

    render() {
        const d = this.data;

        // Stats
        document.getElementById('stat-revenue').textContent = this.formatCurrency(d.revenue_month);
        document.getElementById('stat-revenue-change').textContent = `+${d.revenue_change}%`;
        document.getElementById('stat-tickets').textContent = d.tickets_sold;
        document.getElementById('stat-tickets-change').textContent = `+${d.tickets_change}%`;
        document.getElementById('stat-events').textContent = d.active_events;
        document.getElementById('nav-events-count').textContent = d.active_events;
        document.getElementById('stat-conversion').textContent = `${d.conversion_rate}%`;
        document.getElementById('stat-conv-change').textContent = `+${d.conversion_change}%`;

        // Welcome stat
        document.getElementById('welcome-stat').textContent = `Ai vandut ${d.weekly_sales || d.tickets_sold} bilete in ultima saptamana. Continua tot asa!`;

        // Events table
        this.renderEvents(d.events);

        // Upcoming event
        if (d.events?.length) {
            this.renderUpcomingEvent(d.events[0]);
        }

        // Activity
        this.renderActivity(d.recent_activity);

        // Taxes
        if (d.taxes) {
            document.getElementById('tax-musical').textContent = this.formatCurrency(d.taxes.musical);
            document.getElementById('tax-redcross').textContent = this.formatCurrency(d.taxes.redcross);
            document.getElementById('tax-total').textContent = this.formatCurrency(d.taxes.total);
        }

        // Notifications
        this.renderNotifications();
    },

    renderEvents(events) {
        const tbody = document.getElementById('events-tbody');
        const noEvents = document.getElementById('no-events');
        const eventsTable = document.getElementById('events-table');

        if (!events?.length) {
            tbody.innerHTML = '';
            noEvents.classList.remove('hidden');
            eventsTable.classList.add('hidden');
            return;
        }

        noEvents.classList.add('hidden');
        eventsTable.classList.remove('hidden');
        document.getElementById('events-count-text').textContent = `${events.length} evenimente active`;

        tbody.innerHTML = events.map(e => {
            const percent = Math.round((e.tickets_sold / e.tickets_total) * 100);
            const barColor = percent > 50 ? 'bg-success' : percent > 25 ? 'bg-warning' : 'bg-primary';
            const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const date = new Date(e.start_date);

            return `
                <tr class="event-row">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <img src="${e.image || '/assets/images/default-event.png'}" class="w-12 h-12 rounded-xl object-cover" alt="">
                            <div>
                                <p class="font-semibold text-secondary">${e.title}</p>
                                <p class="text-xs text-muted">${e.artist || ''} • ${e.venue || ''}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-secondary">${date.getDate()} ${months[date.getMonth()]}</p>
                        <p class="text-xs text-muted">${date.getFullYear()}</p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-surface rounded-full overflow-hidden w-20">
                                <div class="h-full ${barColor} rounded-full" style="width: ${percent}%"></div>
                            </div>
                            <span class="text-sm font-medium text-secondary">${e.tickets_sold}/${e.tickets_total}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 bg-success/10 text-success text-xs font-semibold rounded-full">Activ</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button class="p-2 hover:bg-surface rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    renderUpcomingEvent(event) {
        const container = document.getElementById('upcoming-event-content');
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const date = new Date(event.start_date);
        const available = event.tickets_total - event.tickets_sold;

        container.innerHTML = `
            <div class="relative mb-4">
                <img src="${event.image || '/assets/images/default-event.png'}" class="w-full h-32 object-cover rounded-xl" alt="">
                <div class="absolute top-3 left-3 px-3 py-1 bg-primary text-white text-xs font-bold rounded-lg">In 3 zile</div>
            </div>
            <h3 class="font-bold text-secondary mb-1">${event.title}</h3>
            <p class="text-sm text-muted mb-4">${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} • ${event.venue || ''}</p>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-xl font-bold text-secondary">${event.tickets_sold}</p>
                    <p class="text-xs text-muted">Bilete vandute</p>
                </div>
                <div class="bg-surface rounded-xl p-3 text-center">
                    <p class="text-xl font-bold text-secondary">${available}</p>
                    <p class="text-xs text-muted">Disponibile</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="/organizer/checkin?event=${event.id}" class="flex-1 btn-primary py-2.5 rounded-xl font-semibold text-white text-sm text-center">Check-in</a>
                <a href="/organizer/events?id=${event.id}" class="flex-1 py-2.5 rounded-xl font-semibold text-secondary text-sm text-center border border-border hover:border-primary transition-colors">Detalii</a>
            </div>
        `;
    },

    renderActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!activities?.length) {
            container.innerHTML = '<p class="text-center text-muted py-4">Nicio activitate recenta</p>';
            return;
        }

        const icons = {
            sale: { bg: 'bg-success/10', color: 'text-success', path: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            promo: { bg: 'bg-accent/10', color: 'text-accent', path: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' }
        };

        container.innerHTML = activities.slice(0, 4).map(a => {
            const icon = icons[a.type] || icons.sale;
            return `
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 ${icon.bg} rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 ${icon.color}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${icon.path}"/></svg>
                    </div>
                    <div>
                        <p class="text-sm text-secondary">${a.message}</p>
                        <p class="text-xs text-muted">${a.details}</p>
                        <p class="text-xs text-muted mt-1">${a.time}</p>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderNotifications() {
        const container = document.getElementById('notifications-list');
        container.innerHTML = `
            <a href="#" class="flex gap-3 p-4 hover:bg-surface transition-colors border-l-2 border-primary bg-primary/5">
                <div class="w-10 h-10 bg-success/10 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary">Plata primita: 2,450 lei</p>
                    <p class="text-xs text-muted mt-0.5">Transfer saptamanal procesat</p>
                    <p class="text-xs text-muted mt-1">Acum 2 ore</p>
                </div>
            </a>
            <a href="#" class="flex gap-3 p-4 hover:bg-surface transition-colors border-l-2 border-primary bg-primary/5">
                <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary">15 bilete vandute</p>
                    <p class="text-xs text-muted mt-0.5">Dirty Shirt - Mos Craciun e Rocker</p>
                    <p class="text-xs text-muted mt-1">Acum 4 ore</p>
                </div>
            </a>
        `;
    },

    initChart() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        this.salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.sales_chart?.labels || [],
                datasets: [{
                    label: 'Bilete vandute',
                    data: this.data.sales_chart?.tickets || [],
                    borderColor: '#A51C30',
                    backgroundColor: 'rgba(165, 28, 48, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#A51C30',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }, {
                    label: 'Venituri (lei)',
                    data: this.data.sales_chart?.revenue || [],
                    borderColor: '#10B981',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 0,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 12, padding: 20, font: { family: 'Plus Jakarta Sans', size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748B' } },
                    y: { beginAtZero: true, position: 'left', grid: { color: '#E2E8F0' }, ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748B' } },
                    y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#10B981', callback: v => v + ' lei' } }
                }
            }
        });
    },

    setupChartPeriod() {
        document.querySelectorAll('.chart-period').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.chart-period').forEach(b => {
                    b.classList.remove('bg-primary/10', 'text-primary');
                    b.classList.add('text-muted');
                });
                btn.classList.add('bg-primary/10', 'text-primary');
                btn.classList.remove('text-muted');
            });
        });
    },

    logout() {
        AmbiletAuth.logoutOrganizer();
        window.location.href = '/organizer/login';
    },

    getInitials(firstName, lastName) {
        return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || 'EM';
    },

    formatCurrency(amount) {
        return new Intl.NumberFormat('ro-RO').format(amount) + ' lei';
    }
};

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', () => OrgDashboard.init());
</script>
JS;

require_once dirname(__DIR__) . '/includes/scripts.php';
?>
