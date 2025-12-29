<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Comenzile mele';
$currentPage = 'orders';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-6 lg:py-8">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Comenzile mele</h1>
                <p class="text-muted text-sm mt-1">Istoric complet al achizitiilor tale</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="filter-status" class="px-4 py-2 bg-surface border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate comenzile</option>
                    <option value="confirmed">Confirmate</option>
                    <option value="pending">In asteptare</option>
                    <option value="refunded">Rambursate</option>
                </select>
            </div>
        </div>

        <!-- Orders Stats -->
        <div class="grid grid-cols-3 gap-3 lg:gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 border border-border text-center">
                <p id="stat-total" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-xs text-muted">Total comenzi</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-border text-center">
                <p id="stat-spent" class="text-2xl font-bold text-success">0 lei</p>
                <p class="text-xs text-muted">Cheltuit total</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-border text-center">
                <p id="stat-saved" class="text-2xl font-bold text-accent">0 lei</p>
                <p class="text-xs text-muted">Economisit</p>
            </div>
        </div>

        <!-- Orders List -->
        <div id="orders-list" class="space-y-4">
            <div class="text-center py-8">
                <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
                <p class="text-muted mt-2">Se incarca comenzile...</p>
            </div>
        </div>

        <!-- Load More -->
        <div id="load-more" class="hidden text-center mt-8">
            <button class="px-6 py-2.5 bg-surface text-secondary font-medium rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                Incarca mai multe comenzi
            </button>
        </div>
    </main>

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
            window.location.href = '/login?redirect=/user/orders';
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
            const response = await AmbiletAPI.get('/customer/orders');
            if (response.success) {
                this.orders = response.data;
            }
        } catch (error) {
            console.log('Using demo data');
            // Demo data
            this.orders = [
                {
                    id: 1,
                    reference: 'TIX-78453',
                    status: 'confirmed',
                    event: { title: 'Mos Craciun e Rocker', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200' },
                    created_at: '2024-12-22T14:35:00',
                    items: [{ name: 'VIP', quantity: 2, price: 150 }],
                    subtotal: 300,
                    discount: 30,
                    discount_code: 'ROCK2024',
                    total: 270,
                    points_earned: 60,
                    payment_method: 'Card •••• 4532',
                    payment_date: '2024-12-22T14:36:00',
                    transaction_id: 'TRX-8F4A2B'
                },
                {
                    id: 2,
                    reference: 'TIX-78501',
                    status: 'confirmed',
                    event: { title: 'Cargo Live', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200' },
                    created_at: '2024-12-20T10:12:00',
                    items: [{ name: 'Standard', quantity: 1, price: 80 }],
                    subtotal: 80,
                    discount: 0,
                    total: 80,
                    points_earned: 16,
                    payment_method: 'Apple Pay',
                    payment_date: '2024-12-20T10:13:00'
                },
                {
                    id: 3,
                    reference: 'TIX-77234',
                    status: 'completed',
                    event: { title: 'Halloween Rock Night', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200' },
                    created_at: '2024-10-28T18:45:00',
                    items: [{ name: 'Standard', quantity: 2, price: 80 }],
                    subtotal: 160,
                    total: 160,
                    points_earned: 32,
                    checked_in: true
                },
                {
                    id: 4,
                    reference: 'TIX-76890',
                    status: 'refunded',
                    event: { title: 'Concert Anulat - Festival X', image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=200' },
                    created_at: '2024-09-15T09:30:00',
                    items: [{ name: 'Premium', quantity: 1, price: 200 }],
                    subtotal: 200,
                    total: 200,
                    refunded_amount: 200,
                    refund_date: '2024-09-16',
                    refund_reason: 'Eveniment anulat de organizator'
                },
                {
                    id: 5,
                    reference: 'TIX-75123',
                    status: 'completed',
                    event: { title: 'Trooper - 30 Years Tour', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200' },
                    created_at: '2024-09-10T12:20:00',
                    items: [{ name: 'VIP', quantity: 1, price: 150 }],
                    subtotal: 150,
                    total: 150,
                    points_earned: 30
                }
            ];
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
                <div class="text-center py-12 bg-white rounded-xl border border-border">
                    <p class="text-muted mb-4">Nu ai comenzi ${filter ? 'in aceasta categorie' : 'inca'}</p>
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
            <div class="order-card bg-white rounded-xl border border-border overflow-hidden">
                <button onclick="toggleOrder(this)" class="w-full p-4 lg:p-5 text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 ${isPast ? 'grayscale opacity-75' : ''}">
                            <img src="${order.event.image}" class="w-full h-full object-cover" alt="">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono text-muted">#${order.reference}</span>
                                <span class="px-2 py-0.5 ${statusClass} text-xs font-bold rounded">${statusLabel}</span>
                            </div>
                            <h3 class="font-semibold text-secondary mt-1">${order.event.title}</h3>
                            <p class="text-sm text-muted">${this.formatDateTime(order.created_at)} • ${order.items.reduce((sum, i) => sum + i.quantity, 0)} bilete ${order.items[0].name}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            ${order.status === 'refunded' ?
                                `<p class="font-bold text-muted line-through">${order.total} lei</p><p class="text-xs text-error">Rambursat</p>` :
                                `<p class="font-bold text-secondary">${order.total} lei</p>${order.points_earned ? `<p class="text-xs text-success">+${order.points_earned} puncte</p>` : ''}`
                            }
                        </div>
                        <svg class="expand-icon w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>

                <div class="order-details border-t border-border">
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
                <h4 class="text-sm font-semibold text-secondary mb-3">Status comanda</h4>
                <div class="flex items-center gap-2">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-success rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="w-12 h-1 bg-success"></div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-success rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="w-12 h-1 bg-success"></div>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-success rounded-full flex items-center justify-center">
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
                <div class="flex items-center gap-2 p-3 bg-success/10 rounded-lg">
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-medium text-success">Check-in efectuat</span>
                </div>
            </div>
            ` : ''}

            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <h4 class="text-sm font-semibold text-secondary mb-2">Detalii bilete</h4>
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
                    <h4 class="text-sm font-semibold text-secondary mb-2">Informatii plata</h4>
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
                            <span class="text-secondary font-mono text-xs">${order.transaction_id}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
            </div>

            <div class="flex flex-wrap gap-2 pt-4 border-t border-border">
                ${order.status === 'confirmed' ? `
                <a href="/user/tickets" class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    Vezi biletele
                </a>
                ` : ''}
                <button class="flex items-center gap-2 px-4 py-2 bg-surface text-secondary rounded-lg text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarca factura
                </button>
                <button class="flex items-center gap-2 px-4 py-2 text-muted rounded-lg text-sm font-medium hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Ajutor
                </button>
            </div>
        `;
    },

    renderRefundDetails(order) {
        return `
            <div class="p-4 bg-error/5 border border-error/20 rounded-xl mb-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-error/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-error">${order.refund_reason || 'Comanda rambursata'}</p>
                        <p class="text-sm text-muted mt-1">Rambursarea a fost procesata automat in contul tau pe ${order.refund_date}.</p>
                    </div>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-sm font-semibold text-secondary mb-2">Detalii rambursare</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">Suma rambursata</span>
                            <span class="text-success font-medium">${order.refunded_amount} lei</span>
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
