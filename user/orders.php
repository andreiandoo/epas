<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Comenzile mele';
$currentPage = 'orders';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .order-details { display: none; }
    .order-card.expanded .order-details { display: block; }
    .order-card .expand-icon { transition: transform 0.2s ease; }
    .order-card.expanded .expand-icon { transform: rotate(180deg); }
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
                <h1 class="text-2xl font-bold text-secondary">Comenzile mele</h1>
                <p class="mt-1 text-sm text-muted">Istoric complet al achizitiilor tale</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="filter-status" class="px-4 py-2 text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate comenzile</option>
                    <option value="confirmed">Confirmate</option>
                    <option value="pending">In asteptare</option>
                    <option value="refunded">Rambursate</option>
                </select>
            </div>
        </div>

        <!-- Orders Stats -->
        <div class="grid grid-cols-3 gap-3 mb-6 lg:gap-4">
            <div class="p-4 text-center bg-white border rounded-xl border-border">
                <p id="stat-total" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-xs text-muted">Total comenzi</p>
            </div>
            <div class="p-4 text-center bg-white border rounded-xl border-border">
                <p id="stat-spent" class="text-2xl font-bold text-success">0 lei</p>
                <p class="text-xs text-muted">Cheltuit total</p>
            </div>
            <div class="p-4 text-center bg-white border rounded-xl border-border">
                <p id="stat-saved" class="text-2xl font-bold text-accent">0 lei</p>
                <p class="text-xs text-muted">Economisit</p>
            </div>
        </div>

        <!-- Orders List -->
        <div id="orders-list" class="space-y-4">
            <div class="py-8 text-center">
                <div class="w-8 h-8 mx-auto border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <p class="mt-2 text-muted">Se incarca comenzile...</p>
            </div>
        </div>

        <!-- Load More -->
        <div id="load-more" class="hidden mt-8 text-center">
            <button class="px-6 py-2.5 bg-surface text-secondary font-medium rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                Incarca mai multe comenzi
            </button>
        </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
function toggleOrder(btn) {
    const card = btn.closest('.order-card');
    card.classList.toggle('expanded');
}

const UserOrders = {
    orders: [],

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/comenzi';
            return;
        }
        this.loadUserInfo();
        await this.loadOrders();

        document.getElementById('filter-status').addEventListener('change', () => this.filterOrders());
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = (user.points || 0).toLocaleString();
            }
        }
    },

    async loadOrders() {
        try {
            const response = await AmbiletAPI.customer.getOrders();
            if (response.success && response.data) {
                this.orders = response.data.orders || response.data || [];
            } else {
                this.loadDemoData();
                return; // loadDemoData handles rendering
            }
        } catch (error) {
            console.log('Using demo data:', error);
            this.loadDemoData();
            return; // loadDemoData handles rendering
        }

        this.updateStats();
        this.renderOrders();
    },

    loadDemoData() {
        // Load from centralized DEMO_DATA
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.customerOrders) {
            this.orders = DEMO_DATA.customerOrders;
        } else {
            console.warn('DEMO_DATA.customerOrders not found');
            this.orders = [];
        }

        this.updateStats();
        this.renderOrders();
    },

    updateStats() {
        const confirmed = this.orders.filter(o => o.status !== 'refunded');
        const totalSpent = confirmed.reduce((sum, o) => sum + o.total, 0);
        const totalSaved = confirmed.reduce((sum, o) => sum + (o.discount || 0), 0);

        document.getElementById('stat-total').textContent = this.orders.length;
        document.getElementById('stat-spent').textContent = totalSpent.toLocaleString() + ' lei';
        document.getElementById('stat-saved').textContent = totalSaved.toLocaleString() + ' lei';
    },

    filterOrders() {
        this.renderOrders();
    },

    renderOrders() {
        const container = document.getElementById('orders-list');
        const filter = document.getElementById('filter-status').value;

        let filtered = this.orders;
        if (filter) {
            filtered = this.orders.filter(o => o.status === filter);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="py-12 text-center bg-white border rounded-xl border-border">
                    <p class="mb-4 text-muted">Nu ai comenzi ${filter ? 'in aceasta categorie' : 'inca'}</p>
                    <a href="/" class="btn btn-primary inline-flex px-6 py-2.5 text-white font-semibold rounded-xl">Descopera evenimente</a>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(order => this.renderOrderCard(order)).join('');
        document.getElementById('load-more').classList.remove('hidden');
    },

    renderOrderCard(order) {
        const statusClass = {
            'confirmed': 'bg-success/10 text-success',
            'completed': 'bg-muted/20 text-muted',
            'pending': 'bg-warning/10 text-warning',
            'refunded': 'bg-error/10 text-error'
        }[order.status] || 'bg-muted/20 text-muted';

        const statusLabel = {
            'confirmed': 'CONFIRMAT',
            'completed': 'INCHEIAT',
            'pending': 'IN ASTEPTARE',
            'refunded': 'RAMBURSAT'
        }[order.status] || order.status.toUpperCase();

        const isPast = order.status === 'completed' || order.status === 'refunded';

        return `
            <div class="overflow-hidden bg-white border order-card rounded-xl border-border">
                <button onclick="toggleOrder(this)" class="w-full p-4 text-left lg:p-5">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 ${isPast ? 'grayscale opacity-75' : ''}">
                            <img src="${order.event.image}" class="object-cover w-full h-full" alt="">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-muted">#${order.reference}</span>
                                <span class="px-2 py-0.5 ${statusClass} text-xs font-bold rounded">${statusLabel}</span>
                            </div>
                            <h3 class="mt-1 font-semibold text-secondary">${order.event.title}</h3>
                            <p class="text-sm text-muted">${this.formatDateTime(order.created_at)} • ${order.items.reduce((sum, i) => sum + i.quantity, 0)} bilete ${order.items[0].name}</p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            ${order.status === 'refunded' ?
                                `<p class="font-bold line-through text-muted">${order.total} lei</p><p class="text-xs text-error">Rambursat</p>` :
                                `<p class="font-bold text-secondary">${order.total} lei</p>${order.points_earned ? `<p class="text-xs text-success">+${order.points_earned} puncte</p>` : ''}`
                            }
                        </div>
                        <svg class="w-5 h-5 expand-icon text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>

                <div class="border-t order-details border-border">
                    <div class="p-4 lg:p-5 bg-surface/50">
                        ${order.status === 'refunded' ? this.renderRefundDetails(order) : this.renderOrderDetails(order)}
                    </div>
                </div>
            </div>
        `;
    },

    renderOrderDetails(order) {
        return `
            ${order.status === 'confirmed' ? `
            <div class="mb-6">
                <h4 class="mb-3 text-sm font-semibold text-secondary">Status comanda</h4>
                <div class="flex items-center gap-2">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-success">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="w-12 h-1 bg-success"></div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-success">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="w-12 h-1 bg-success"></div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-success">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-muted">
                    <span>Comanda plasata</span>
                    <span>Plata confirmata</span>
                    <span>Bilete emise</span>
                </div>
            </div>
            ` : ''}

            ${order.checked_in ? `
            <div class="mb-4">
                <div class="flex items-center gap-2 p-3 rounded-lg bg-success/10">
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-medium text-success">Check-in efectuat</span>
                </div>
            </div>
            ` : ''}

            <div class="grid gap-4 mb-4 sm:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-sm font-semibold text-secondary">Detalii bilete</h4>
                    <div class="space-y-2">
                        ${order.items.map(item => `
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">${item.quantity}x ${item.name}</span>
                            <span class="text-secondary">${item.quantity * item.price} lei</span>
                        </div>
                        `).join('')}
                        ${order.discount ? `
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Cod promotional ${order.discount_code ? `(${order.discount_code})` : ''}</span>
                            <span class="text-success">-${order.discount} lei</span>
                        </div>
                        ` : ''}
                        <hr class="border-border">
                        <div class="flex justify-between font-semibold">
                            <span class="text-secondary">Total platit</span>
                            <span class="text-secondary">${order.total} lei</span>
                        </div>
                    </div>
                </div>
                ${order.payment_method ? `
                <div>
                    <h4 class="mb-2 text-sm font-semibold text-secondary">Informatii plata</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">Metoda</span>
                            <span class="text-secondary">${order.payment_method}</span>
                        </div>
                        ${order.payment_date ? `
                        <div class="flex justify-between">
                            <span class="text-muted">Data platii</span>
                            <span class="text-secondary">${this.formatDateTime(order.payment_date)}</span>
                        </div>
                        ` : ''}
                        ${order.transaction_id ? `
                        <div class="flex justify-between">
                            <span class="text-muted">ID tranzactie</span>
                            <span class="font-mono text-xs text-secondary">${order.transaction_id}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
            </div>

            <div class="flex flex-wrap gap-2 pt-4 border-t border-border">
                ${order.status === 'confirmed' ? `
                <a href="/cont/bilete" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white transition-colors rounded-lg bg-primary hover:bg-primary-dark">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    Vezi biletele
                </a>
                ` : ''}
                <button class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors rounded-lg bg-surface text-secondary hover:bg-primary/10 hover:text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarca factura
                </button>
                <button class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors rounded-lg text-muted hover:bg-surface">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Ajutor
                </button>
            </div>
        `;
    },

    renderRefundDetails(order) {
        return `
            <div class="p-4 mb-4 border bg-error/5 border-error/20 rounded-xl">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-error/10">
                        <svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-error">${order.refund_reason || 'Comanda rambursata'}</p>
                        <p class="mt-1 text-sm text-muted">Rambursarea a fost procesata automat in contul tau pe ${order.refund_date}.</p>
                    </div>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-sm font-semibold text-secondary">Detalii rambursare</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">Suma rambursata</span>
                            <span class="font-medium text-success">${order.refunded_amount} lei</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Data rambursarii</span>
                            <span class="text-secondary">${order.refund_date}</span>
                        </div>
                        ${order.points_earned ? `
                        <div class="flex justify-between">
                            <span class="text-muted">Puncte returnate</span>
                            <span class="text-secondary">-${order.points_earned} puncte</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    },

    formatDateTime(dateStr) {
        const date = new Date(dateStr);
        const day = date.getDate();
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const hours = date.getHours().toString().padStart(2, '0');
        const mins = date.getMinutes().toString().padStart(2, '0');
        return `${day} ${months[date.getMonth()]} ${date.getFullYear()}, ${hours}:${mins}`;
    },

    logout() {
        AmbiletAuth.logout();
        window.location.href = '/login';
    }
};

document.addEventListener('DOMContentLoaded', () => UserOrders.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
