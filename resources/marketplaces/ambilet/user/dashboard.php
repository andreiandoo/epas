<?php
/**
 * User Dashboard
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Dashboard';
$bodyClass = 'min-h-screen bg-surface';
$currentPage = 'dashboard';

require_once dirname(__DIR__) . '/includes/head.php';
?>

    <div id="header-container"></div>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <?php require_once dirname(__DIR__) . '/includes/user-sidebar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1">
                <!-- Welcome Section -->
                <div class="bg-gradient-to-r from-primary to-primary-dark rounded-2xl p-8 text-white mb-8">
                    <h1 class="text-2xl font-bold mb-2">Bine ai venit, <span id="welcome-name">utilizator</span>!</h1>
                    <p class="text-white/80">Gestioneaza biletele, vezi comenzile si acumuleaza puncte.</p>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-secondary" id="stat-tickets">0</p>
                                <p class="text-sm text-muted">Bilete active</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-secondary" id="stat-attended">0</p>
                                <p class="text-sm text-muted">Evenimente vizitate</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-accent/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-secondary" id="stat-points">0</p>
                                <p class="text-sm text-muted">Puncte acumulate</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-border p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-error/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-secondary" id="stat-favorites">0</p>
                                <p class="text-sm text-muted">Favorite</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="bg-white rounded-2xl border border-border p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-secondary">Evenimentele tale</h2>
                        <a href="/user/tickets.php" class="text-primary font-medium hover:underline">Vezi toate</a>
                    </div>

                    <div id="upcoming-events" class="space-y-4">
                        <div class="skeleton h-24 rounded-xl"></div>
                        <div class="skeleton h-24 rounded-xl"></div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-secondary">Comenzi recente</h2>
                        <a href="/user/orders.php" class="text-primary font-medium hover:underline">Vezi toate</a>
                    </div>

                    <div id="recent-orders" class="space-y-4">
                        <div class="skeleton h-16 rounded-xl"></div>
                        <div class="skeleton h-16 rounded-xl"></div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="footer"></div>

<?php
$scriptsExtra = <<<'JS'
<script>
const UserDashboard = {
    async init() {
        // Check authentication
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/login.php?redirect=/user/dashboard.php';
            return;
        }

        // Load user data
        const user = AmbiletAuth.getUser();
        if (user) {
            document.getElementById('user-initials').textContent = user.name?.substring(0, 2).toUpperCase() || '--';
            document.getElementById('user-name').textContent = user.name || 'Utilizator';
            document.getElementById('user-email').textContent = user.email || '';
            document.getElementById('welcome-name').textContent = user.first_name || user.name?.split(' ')[0] || 'utilizator';
        }

        // Load dashboard data
        await Promise.all([
            this.loadStats(),
            this.loadUpcomingEvents(),
            this.loadRecentOrders()
        ]);
    },

    async loadStats() {
        try {
            const response = await AmbiletAPI.get('/customer/stats');
            if (response.success) {
                document.getElementById('stat-tickets').textContent = response.data.active_tickets || 0;
                document.getElementById('stat-attended').textContent = response.data.attended_events || 0;
                document.getElementById('stat-points').textContent = response.data.points || 0;
                document.getElementById('stat-favorites').textContent = response.data.favorites || 0;
            }
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    },

    async loadUpcomingEvents() {
        const container = document.getElementById('upcoming-events');
        try {
            const response = await AmbiletAPI.get('/customer/tickets?status=valid&limit=3');
            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(ticket => `
                    <div class="flex items-center gap-4 p-4 bg-surface rounded-xl">
                        <img src="${ticket.event?.image || '/assets/images/placeholder-event.jpg'}" alt="${ticket.event?.title}" class="w-20 h-20 rounded-lg object-cover">
                        <div class="flex-1">
                            <h3 class="font-semibold text-secondary">${ticket.event?.title || 'Eveniment'}</h3>
                            <p class="text-sm text-muted">${AmbiletUtils.formatDate(ticket.event?.date)}</p>
                            <p class="text-sm text-muted">${ticket.ticket_type?.name || ''}</p>
                        </div>
                        <a href="/user/tickets.php?id=${ticket.id}" class="btn btn-secondary btn-sm">
                            Vezi bilet
                        </a>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-muted mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                        <p class="text-muted">Nu ai bilete active</p>
                        <a href="/" class="btn btn-primary mt-4">Descopera evenimente</a>
                    </div>
                `;
            }
        } catch (error) {
            container.innerHTML = '<p class="text-center text-muted py-4">Nu s-au putut incarca biletele</p>';
        }
    },

    async loadRecentOrders() {
        const container = document.getElementById('recent-orders');
        try {
            const response = await AmbiletAPI.get('/customer/orders?limit=3');
            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(order => `
                    <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                        <div>
                            <p class="font-semibold text-secondary">#${order.reference}</p>
                            <p class="text-sm text-muted">${AmbiletUtils.formatDate(order.created_at)}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-secondary">${AmbiletUtils.formatCurrency(order.total)}</p>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${order.status === 'completed' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}">
                                ${order.status === 'completed' ? 'Finalizata' : 'In procesare'}
                            </span>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-center text-muted py-4">Nu ai comenzi inca</p>';
            }
        } catch (error) {
            container.innerHTML = '<p class="text-center text-muted py-4">Nu s-au putut incarca comenzile</p>';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => UserDashboard.init());
</script>
JS;

require_once dirname(__DIR__) . '/includes/scripts.php';
?>
