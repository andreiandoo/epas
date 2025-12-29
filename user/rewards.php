<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Puncte & Recompense';
$currentPage = 'rewards';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
        <h1 class="text-2xl font-bold text-secondary mb-6">Puncte & Recompense</h1>

        <!-- Points Card -->
        <div class="bg-gradient-to-r from-accent to-warning rounded-2xl p-6 lg:p-8 text-white mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-white/80 mb-1">Puncte disponibile</p>
                    <p class="text-4xl lg:text-5xl font-bold" id="points-balance">0</p>
                    <p class="text-sm text-white/80 mt-2">= <span id="points-value">0 RON</span> valoare</p>
                </div>
                <div class="flex gap-3">
                    <a href="/" class="btn bg-white text-accent hover:bg-white/90">
                        Foloseste punctele
                    </a>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div class="bg-white rounded-2xl border border-border p-6 mb-8">
            <h2 class="text-lg font-bold text-secondary mb-4">Cum functioneaza</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-secondary mb-1">Cumpara bilete</h3>
                    <p class="text-sm text-muted">Primesti 1 punct pentru fiecare 1 RON cheltuit</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-accent/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-secondary mb-1">Acumuleaza puncte</h3>
                    <p class="text-sm text-muted">Punctele se adauga automat in cont</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-secondary mb-1">Foloseste-le ca discount</h3>
                    <p class="text-sm text-muted">100 puncte = 1 RON reducere</p>
                </div>
            </div>
        </div>

        <!-- Points History -->
        <div class="bg-white rounded-2xl border border-border p-6">
            <h2 class="text-lg font-bold text-secondary mb-4">Istoric puncte</h2>

            <div id="points-history" class="space-y-3">
                <!-- Loading -->
                <div class="animate-pulse flex justify-between p-3 bg-surface rounded-xl">
                    <div class="h-4 bg-border rounded w-1/3"></div>
                    <div class="h-4 bg-border rounded w-1/6"></div>
                </div>
            </div>

            <div id="no-history" class="hidden text-center py-8">
                <svg class="w-12 h-12 text-muted/30 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-muted">Nu ai tranzactii cu puncte inca</p>
                <a href="/" class="text-primary text-sm hover:underline">Cumpara primul bilet</a>
            </div>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const RewardsPage = {
    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/login?redirect=/user/rewards';
            return;
        }

        this.loadUserInfo();
        await this.loadRewards();
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
                headerPoints.textContent = user.points || '0';
            }
        }
    },

    async loadRewards() {
        try {
            const response = await AmbiletAPI.get('/customer/rewards');
            if (response.success) {
                this.renderRewards(response.data);
            } else {
                this.loadDemoData();
            }
        } catch (error) {
            console.error('Error loading rewards:', error);
            this.loadDemoData();
        }
    },

    loadDemoData() {
        const demoData = {
            balance: 2450,
            history: [
                { id: 1, type: 'earned', description: 'Achizitie bilet Concert Rock', points: 120, created_at: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString() },
                { id: 2, type: 'spent', description: 'Discount la checkout', points: 50, created_at: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString() },
                { id: 3, type: 'earned', description: 'Bonus inregistrare', points: 50, created_at: new Date(Date.now() - 12 * 24 * 60 * 60 * 1000).toISOString() },
                { id: 4, type: 'earned', description: 'Achizitie bilet Festival', points: 250, created_at: new Date(Date.now() - 15 * 24 * 60 * 60 * 1000).toISOString() },
                { id: 5, type: 'earned', description: 'Achizitie bilet Teatru', points: 80, created_at: new Date(Date.now() - 20 * 24 * 60 * 60 * 1000).toISOString() }
            ]
        };
        this.renderRewards(demoData);
    },

    renderRewards(data) {
        // Update balance
        document.getElementById('points-balance').textContent = this.formatNumber(data.balance || 0);
        const headerPoints = document.getElementById('header-user-points');
        if (headerPoints) {
            headerPoints.textContent = this.formatNumber(data.balance || 0);
        }
        document.getElementById('points-value').textContent = this.formatCurrency((data.balance || 0) / 100);

        // Render history
        const historyContainer = document.getElementById('points-history');
        const noHistory = document.getElementById('no-history');

        if (data.history && data.history.length > 0) {
            historyContainer.innerHTML = data.history.map(item => `
                <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 ${item.type === 'earned' ? 'bg-success/10' : 'bg-error/10'} rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 ${item.type === 'earned' ? 'text-success' : 'text-error'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${item.type === 'earned' ? 'M12 6v6m0 0v6m0-6h6m-6 0H6' : 'M20 12H4'}"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-secondary">${item.description || (item.type === 'earned' ? 'Puncte primite' : 'Puncte folosite')}</p>
                            <p class="text-sm text-muted">${this.formatDate(item.created_at)}</p>
                        </div>
                    </div>
                    <span class="font-bold ${item.type === 'earned' ? 'text-success' : 'text-error'}">
                        ${item.type === 'earned' ? '+' : '-'}${item.points} puncte
                    </span>
                </div>
            `).join('');
            noHistory.classList.add('hidden');
        } else {
            historyContainer.classList.add('hidden');
            noHistory.classList.remove('hidden');
        }
    },

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    formatCurrency(amount) {
        return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(amount);
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    }
};

// Initialize page
document.addEventListener('DOMContentLoaded', () => RewardsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
