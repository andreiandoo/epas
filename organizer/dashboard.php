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
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    
        <main class="flex-1">
            <!-- Welcome Banner -->
            <div class="relative p-6 mb-8 overflow-hidden text-white bg-gradient-to-r from-primary to-primary-dark">
                <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
                <div class="absolute bottom-0 w-32 h-32 translate-y-1/2 rounded-full right-20 bg-white/5"></div>
                <div class="relative">
                    <h1 class="mb-2 text-2xl font-bold md:text-3xl">Bun venit inapoi! 👋</h1>
                    <p id="welcome-stat" class="mb-4 text-white/80">Ai vandut 0 bilete in ultima saptamana. Continua tot asa!</p>
                </div>
            </div>

            <div class="p-4 lg:p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4">
                    <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-success/10 rounded-xl">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span id="stat-revenue-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                        </div>
                        <p id="stat-revenue" class="text-2xl font-bold text-secondary">0 lei</p>
                        <p class="mt-1 text-sm text-muted">Venituri luna aceasta</p>
                    </div>

                    <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-primary/10 rounded-xl">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <span id="stat-tickets-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                        </div>
                        <p id="stat-tickets" class="text-2xl font-bold text-secondary">0</p>
                        <p class="mt-1 text-sm text-muted">Bilete vandute</p>
                    </div>

                    <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-accent/10 rounded-xl">
                                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <p id="stat-events" class="text-2xl font-bold text-secondary">0</p>
                        <p class="mt-1 text-sm text-muted">Evenimente active</p>
                    </div>

                    <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-blue-500/10 rounded-xl">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <span id="stat-conv-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                        </div>
                        <p id="stat-conversion" class="text-2xl font-bold text-secondary">0%</p>
                        <p class="mt-1 text-sm text-muted">Rata conversie</p>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Left Column -->
                    <div class="space-y-8 lg:col-span-2">
                        <!-- Sales Chart -->
                        <div class="p-6 bg-white border rounded-2xl border-border">
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
                        <div class="overflow-hidden bg-white border rounded-2xl border-border">
                            <div class="flex items-center justify-between p-6 border-b border-border">
                                <div>
                                    <h2 class="text-lg font-bold text-secondary">Evenimentele tale</h2>
                                    <p id="events-count-text" class="text-sm text-muted">0 evenimente active</p>
                                </div>
                                <a href="/organizator/events" class="text-sm font-medium text-primary">Vezi toate &rarr;</a>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full" id="events-table">
                                    <thead class="bg-surface">
                                        <tr>
                                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Eveniment</th>
                                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Data</th>
                                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Vanzari</th>
                                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Status</th>
                                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-right uppercase text-muted">Actiuni</th>
                                        </tr>
                                    </thead>
                                    <tbody id="events-tbody" class="divide-y divide-border"></tbody>
                                </table>
                            </div>
                            <div id="no-events" class="hidden py-12 text-center">
                                <svg class="w-12 h-12 mx-auto mb-3 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="mb-3 text-muted">Nu ai evenimente inca</p>
                                <a href="/organizator/events?new=1" class="px-4 py-2 text-sm font-medium text-white btn-primary rounded-xl">Creeaza primul eveniment</a>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-8">
                        <!-- Upcoming Event -->
                        <div class="overflow-hidden bg-white border rounded-2xl border-border" id="upcoming-event-card">
                            <div class="p-5 border-b border-border">
                                <h2 class="font-bold text-secondary">Urmatorul eveniment</h2>
                            </div>
                            <div id="upcoming-event-content" class="p-5"></div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="p-5 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 font-bold text-secondary">Actiuni rapide</h2>
                            <div class="space-y-2">
                                <a href="/organizator/events?new=1" class="flex items-center gap-3 p-3 transition-colors rounded-xl hover:bg-surface">
                                    <div class="flex items-center justify-center w-10 h-10 bg-primary/10 rounded-xl">
                                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Creeaza eveniment</p>
                                        <p class="text-xs text-muted">Adauga un eveniment nou</p>
                                    </div>
                                </a>
                                <a href="/organizator/reports" class="flex items-center gap-3 p-3 transition-colors rounded-xl hover:bg-surface">
                                    <div class="flex items-center justify-center w-10 h-10 bg-success/10 rounded-xl">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Descarca raport</p>
                                        <p class="text-xs text-muted">Export vanzari si taxe</p>
                                    </div>
                                </a>
                                <a href="/organizator/promo" class="flex items-center gap-3 p-3 transition-colors rounded-xl hover:bg-surface">
                                    <div class="flex items-center justify-center w-10 h-10 bg-accent/10 rounded-xl">
                                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Cod promotional</p>
                                        <p class="text-xs text-muted">Creeaza reduceri</p>
                                    </div>
                                </a>
                                <a href="/organizator/email" class="flex items-center gap-3 p-3 transition-colors rounded-xl hover:bg-surface">
                                    <div class="flex items-center justify-center w-10 h-10 bg-blue-500/10 rounded-xl">
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-secondary">Trimite email</p>
                                        <p class="text-xs text-muted">Notifica participantii</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="p-5 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 font-bold text-secondary">Activitate recenta</h2>
                            <div id="recent-activity" class="space-y-4"></div>
                            <a href="/organizator/activity" class="block mt-4 text-sm font-medium text-center text-primary">Vezi toata activitatea</a>
                        </div>
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
        this.org = AmbiletAuth.getOrganizerData();
        this.renderOrgInfo();
        await this.loadDashboard();
        this.initChart();
        this.setupChartPeriod();
    },

    renderOrgInfo() {
        if (!this.org) return;
        const name = this.org.company_name || this.org.name || `${this.org.first_name || ''} ${this.org.last_name || ''}`.trim() || 'Organizator';
        const initials = this.getInitials(this.org.first_name, this.org.last_name);

        // Sidebar elements (use correct IDs from organizer-sidebar.php)
        const sidebarInitials = document.getElementById('sidebar-org-initials');
        const sidebarName = document.getElementById('sidebar-org-name');
        if (sidebarInitials) sidebarInitials.textContent = initials;
        if (sidebarName) sidebarName.textContent = name;

        // Topbar elements (use correct IDs from organizer-topbar.php)
        const topbarInitials = document.getElementById('topbar-org-initials');
        const topbarName = document.getElementById('topbar-org-name');
        const topbarEmail = document.getElementById('topbar-org-email');
        if (topbarInitials) topbarInitials.textContent = initials;
        if (topbarName) topbarName.textContent = name;
        if (topbarEmail) topbarEmail.textContent = this.org.email || '';
    },

    async loadDashboard() {
        try {
            const response = await AmbiletAPI.get('/organizer/dashboard');
            if (response.success && response.data) {
                this.data = response.data;
                this.render();
                return;
            }
        } catch (e) {
            console.error('Failed to load dashboard:', e);
        }

        // Empty data fallback
        this.data = {
            revenue_month: 0,
            tickets_sold: 0,
            active_events: 0,
            events_list: [],
            recent_activity: []
        };
        this.render();
    },

    render() {
        const d = this.data;

        // Stats (handle both new API format and fallback demo data)
        const revenueMonth = d.revenue_month ?? d.sales?.gross_revenue ?? 0;
        const ticketsSold = d.tickets_sold ?? d.sales?.tickets_sold ?? 0;
        const activeEvents = d.active_events ?? d.events?.upcoming ?? 0;
        const conversionRate = d.conversion_rate ?? 0;
        const revenueChange = d.revenue_change ?? 0;
        const ticketsChange = d.tickets_change ?? 0;
        const convChange = d.conversion_change ?? 0;

        document.getElementById('stat-revenue').textContent = this.formatCurrency(revenueMonth);
        document.getElementById('stat-revenue-change').textContent = `+${revenueChange}%`;
        document.getElementById('stat-tickets').textContent = ticketsSold;
        document.getElementById('stat-tickets-change').textContent = `+${ticketsChange}%`;
        document.getElementById('stat-events').textContent = activeEvents;
        document.getElementById('nav-events-count').textContent = activeEvents;
        document.getElementById('stat-conversion').textContent = `${conversionRate}%`;
        document.getElementById('stat-conv-change').textContent = `+${convChange}%`;

        // Welcome stat
        document.getElementById('welcome-stat').textContent = `Ai vandut ${d.weekly_sales || ticketsSold} bilete in ultima saptamana. Continua tot asa!`;

        // Events table - use events_list from API or events from demo data
        const eventsList = d.events_list ?? d.events ?? [];
        this.renderEvents(eventsList);

        // Upcoming event
        if (eventsList?.length) {
            this.renderUpcomingEvent(eventsList[0]);
        }

        // Activity
        this.renderActivity(d.recent_activity);

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
            const ticketsSold = e.tickets_sold || 0;
            const ticketsTotal = e.tickets_total || 100;
            const percent = ticketsTotal > 0 ? Math.round((ticketsSold / ticketsTotal) * 100) : 0;
            const barColor = percent > 50 ? 'bg-success' : percent > 25 ? 'bg-warning' : 'bg-primary';
            const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const date = new Date(e.start_date || e.starts_at);
            const eventName = e.name || e.title || 'Eveniment';
            const venueName = e.venue || e.venue_name || '';
            const venueCity = e.venue_city ? `, ${e.venue_city}` : '';

            return `
                <tr class="event-row">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <img src="${getStorageUrl(e.image)}" class="object-cover w-12 h-12 rounded-xl" alt="" onerror="this.src='/assets/images/default-event.png'" loading="lazy">
                            <div>
                                <p class="font-semibold text-secondary">${eventName}</p>
                                <p class="text-xs text-muted">${venueName}${venueCity}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-secondary">${date.getDate()} ${months[date.getMonth()]}</p>
                        <p class="text-xs text-muted">${date.getFullYear()}</p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 w-20 h-2 overflow-hidden rounded-full bg-surface">
                                <div class="h-full ${barColor} rounded-full" style="width: ${percent}%"></div>
                            </div>
                            <span class="text-sm font-medium text-secondary">${ticketsSold}/${ticketsTotal}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 bg-success/10 text-success text-xs font-semibold rounded-full">${e.status === 'published' ? 'Activ' : 'Draft'}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="/organizator/events?id=${e.id}" class="inline-block p-2 transition-colors rounded-lg hover:bg-surface">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
    },

    renderUpcomingEvent(event) {
        const container = document.getElementById('upcoming-event-content');
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const date = new Date(event.start_date || event.starts_at);
        const ticketsSold = event.tickets_sold || 0;
        const ticketsTotal = event.tickets_total || 100;
        const available = ticketsTotal - ticketsSold;
        const eventName = event.name || event.title || 'Eveniment';
        const venueName = event.venue || event.venue_name || '';
        const venueCity = event.venue_city ? `, ${event.venue_city}` : '';

        // Calculate days until event
        const now = new Date();
        const diffTime = date - now;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        let daysLabel = '';
        if (diffDays === 0) daysLabel = 'Astazi';
        else if (diffDays === 1) daysLabel = 'Maine';
        else if (diffDays > 0) daysLabel = `In ${diffDays} zile`;
        else daysLabel = 'Incheiat';

        container.innerHTML = `
            <div class="relative mb-4">
                <img src="${getStorageUrl(event.image)}" class="object-cover w-full h-32 rounded-xl" alt="" onerror="this.src='/assets/images/default-event.png'" loading="lazy">
                ${diffDays >= 0 ? `<div class="absolute px-3 py-1 text-xs font-bold text-white rounded-lg top-3 left-3 bg-primary">${daysLabel}</div>` : ''}
            </div>
            <h3 class="mb-1 font-bold text-secondary">${eventName}</h3>
            <p class="mb-4 text-sm text-muted">${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} • ${venueName}${venueCity}</p>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="p-3 text-center bg-surface rounded-xl">
                    <p class="text-xl font-bold text-secondary">${ticketsSold}</p>
                    <p class="text-xs text-muted">Bilete vandute</p>
                </div>
                <div class="p-3 text-center bg-surface rounded-xl">
                    <p class="text-xl font-bold text-secondary">${available}</p>
                    <p class="text-xs text-muted">Disponibile</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="/organizator/participanti" class="flex-1 btn-primary py-2.5 rounded-xl font-semibold text-white text-sm text-center">Check-in</a>
                <a href="/organizator/events?id=${event.id}" class="flex-1 py-2.5 rounded-xl font-semibold text-secondary text-sm text-center border border-border hover:border-primary transition-colors">Detalii</a>
            </div>
        `;
    },

    renderActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!activities?.length) {
            container.innerHTML = '<p class="py-4 text-center text-muted">Nicio activitate recenta</p>';
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
                        <p class="mt-1 text-xs text-muted">${a.time}</p>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderNotifications() {
        const container = document.getElementById('notifications-list');
        container.innerHTML = `
            <a href="#" class="flex gap-3 p-4 transition-colors border-l-2 hover:bg-surface border-primary bg-primary/5">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-success/10">
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary">Plata primita: 2,450 lei</p>
                    <p class="text-xs text-muted mt-0.5">Transfer saptamanal procesat</p>
                    <p class="mt-1 text-xs text-muted">Acum 2 ore</p>
                </div>
            </a>
            <a href="#" class="flex gap-3 p-4 transition-colors border-l-2 hover:bg-surface border-primary bg-primary/5">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-primary/10">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary">15 bilete vandute</p>
                    <p class="text-xs text-muted mt-0.5">Dirty Shirt - Mos Craciun e Rocker</p>
                    <p class="mt-1 text-xs text-muted">Acum 4 ore</p>
                </div>
            </a>
        `;
    },

    initChart() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        this.salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Comenzi',
                    data: [],
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
                    data: [],
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
        // Load initial data for 7 days
        this.loadChartData(7);
    },

    setupChartPeriod() {
        document.querySelectorAll('.chart-period').forEach(btn => {
            btn.addEventListener('click', async () => {
                // Update button styles
                document.querySelectorAll('.chart-period').forEach(b => {
                    b.classList.remove('bg-primary/10', 'text-primary');
                    b.classList.add('text-muted');
                });
                btn.classList.add('bg-primary/10', 'text-primary');
                btn.classList.remove('text-muted');

                // Load chart data for selected period
                const days = parseInt(btn.dataset.days) || 7;
                await this.loadChartData(days);
            });
        });
    },

    async loadChartData(days = 7) {
        try {
            const toDate = new Date().toISOString().split('T')[0];
            const fromDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

            const response = await AmbiletAPI.get(`/organizer/dashboard/sales-timeline?from_date=${fromDate}&to_date=${toDate}&group_by=day`);
            if (response.success && response.data?.timeline) {
                const timeline = response.data.timeline;
                const labels = timeline.map(t => {
                    const d = new Date(t.period);
                    return `${d.getDate()}/${d.getMonth() + 1}`;
                });
                const tickets = timeline.map(t => t.orders || 0);
                const revenue = timeline.map(t => parseFloat(t.revenue) || 0);

                // Update chart
                if (this.salesChart) {
                    this.salesChart.data.labels = labels;
                    this.salesChart.data.datasets[0].data = tickets;
                    this.salesChart.data.datasets[1].data = revenue;
                    this.salesChart.update();
                }
            }
        } catch (e) {
            console.error('Failed to load chart data:', e);
        }
    },

    logout() {
        AmbiletAuth.logoutOrganizer();
        window.location.href = '/organizator/login';
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
