<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Carduri Cadou';
$currentPage = 'gift-cards';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>

    <!-- Page Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-secondary">Carduri Cadou</h1>
        <a href="/card-cadou" class="inline-flex items-center gap-2 btn btn-primary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Cumpara card cadou
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
        <div class="p-4 bg-white border rounded-xl border-border">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center justify-center w-10 h-10 bg-purple-100 rounded-lg">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="2"/><path stroke-width="2" d="M12 8v12"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-secondary" id="stat-purchased">0</div>
            <div class="text-xs text-muted">Carduri cumparate</div>
        </div>
        <div class="p-4 bg-white border rounded-xl border-border">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-lg">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-secondary" id="stat-received">0</div>
            <div class="text-xs text-muted">Carduri primite</div>
        </div>
        <div class="p-4 bg-white border rounded-xl border-border">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-lg">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-secondary" id="stat-balance">0 RON</div>
            <div class="text-xs text-muted">Balanta disponibila</div>
        </div>
        <div class="p-4 bg-white border rounded-xl border-border">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center justify-center w-10 h-10 bg-orange-100 rounded-lg">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-secondary" id="stat-gifted">0 RON</div>
            <div class="text-xs text-muted">Total oferit cadou</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 p-1.5 mb-6 bg-white border rounded-xl border-border w-fit">
        <button class="px-4 py-2 text-sm font-semibold transition-colors rounded-lg tab-btn active" data-tab="purchased">
            Cumparate <span class="inline-flex items-center justify-center px-1.5 ml-1 text-xs rounded-full bg-primary/10 text-primary" id="tab-purchased-count">0</span>
        </button>
        <button class="px-4 py-2 text-sm font-semibold transition-colors rounded-lg tab-btn text-muted hover:text-secondary" data-tab="received">
            Primite <span class="inline-flex items-center justify-center px-1.5 ml-1 text-xs rounded-full bg-surface text-muted" id="tab-received-count">0</span>
        </button>
    </div>

    <!-- Purchased Cards Tab -->
    <div class="tab-content active" id="tab-purchased">
        <div class="grid gap-6 md:grid-cols-2" id="purchased-cards">
            <!-- Cards will be loaded here -->
        </div>

        <!-- Empty State -->
        <div class="hidden p-12 text-center bg-white border rounded-2xl border-border" id="empty-purchased">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-primary/10">
                <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="2"/><path stroke-width="2" d="M12 8v12M19 12H5M12 8c-2-2-4-2.5-4-4a2 2 0 114 4M12 8c2-2 4-2.5 4-4a2 2 0 10-4 4"/></svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai cumparat inca carduri cadou</h3>
            <p class="mb-6 text-muted">Ofera un card cadou prietenilor sau familiei tale pentru a le face o surpriza placuta.</p>
            <a href="/card-cadou" class="btn btn-primary">Cumpara primul card cadou</a>
        </div>
    </div>

    <!-- Received Cards Tab -->
    <div class="hidden tab-content" id="tab-received">
        <div class="grid gap-6 md:grid-cols-2" id="received-cards">
            <!-- Cards will be loaded here -->
        </div>

        <!-- Empty State -->
        <div class="hidden p-12 text-center bg-white border rounded-2xl border-border" id="empty-received">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 bg-green-100 rounded-full">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai primit inca carduri cadou</h3>
            <p class="mb-6 text-muted">Cand cineva iti trimite un card cadou, acesta va aparea aici.</p>
        </div>
    </div>
    
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const GiftCardsPage = {
    purchasedCards: [],
    receivedCards: [],

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/carduri-cadou';
            return;
        }

        this.setupTabs();
        this.loadGiftCards();
    },

    setupTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active', 'bg-primary', 'text-white');
                    b.classList.add('text-muted');
                });
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

                btn.classList.add('active', 'bg-primary', 'text-white');
                btn.classList.remove('text-muted');
                const tabId = btn.dataset.tab;
                document.getElementById('tab-' + tabId).classList.remove('hidden');
            });
        });

        // Set initial active state
        const activeBtn = document.querySelector('.tab-btn.active');
        if (activeBtn) {
            activeBtn.classList.add('bg-primary', 'text-white');
            activeBtn.classList.remove('text-muted');
        }
    },

    async loadGiftCards() {
        try {
            const response = await AmbiletAPI.get('/customer/gift-cards');
            if (response.success) {
                this.purchasedCards = response.purchased || [];
                this.receivedCards = response.received || [];
                this.updateStats(response.stats || {});
                this.renderCards();
            }
        } catch (error) {
            console.error('Error loading gift cards:', error);
            this.showEmptyStates();
        }
    },

    updateStats(stats) {
        document.getElementById('stat-purchased').textContent = stats.purchased_count || '0';
        document.getElementById('stat-received').textContent = stats.received_count || '0';
        document.getElementById('stat-balance').textContent = (stats.total_balance || 0) + ' RON';
        document.getElementById('stat-gifted').textContent = (stats.total_gifted || 0) + ' RON';
        document.getElementById('tab-purchased-count').textContent = stats.purchased_count || '0';
        document.getElementById('tab-received-count').textContent = stats.received_count || '0';
    },

    renderCards() {
        this.renderPurchasedCards();
        this.renderReceivedCards();
    },

    renderPurchasedCards() {
        const container = document.getElementById('purchased-cards');
        const emptyState = document.getElementById('empty-purchased');

        if (this.purchasedCards.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.innerHTML = this.purchasedCards.map(card => this.renderPurchasedCard(card)).join('');
    },

    renderReceivedCards() {
        const container = document.getElementById('received-cards');
        const emptyState = document.getElementById('empty-received');

        if (this.receivedCards.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.innerHTML = this.receivedCards.map(card => this.renderReceivedCard(card)).join('');
    },

    renderPurchasedCard(card) {
        const statusClasses = {
            'active': 'bg-green-100 text-green-700',
            'used': 'bg-gray-100 text-gray-600',
            'pending': 'bg-yellow-100 text-yellow-700',
            'sent': 'bg-purple-100 text-purple-700'
        };
        const statusLabels = {
            'active': 'Activ',
            'used': 'Folosit',
            'pending': 'In asteptare',
            'sent': 'Trimis'
        };
        const bgColors = {
            'active': 'from-primary to-primary/80',
            'sent': 'from-purple-600 to-purple-700',
            'used': 'from-gray-500 to-gray-600'
        };

        return `
            <div class="overflow-hidden transition-shadow bg-white border rounded-2xl border-border hover:shadow-lg">
                <div class="relative h-32 p-5 bg-gradient-to-br ${bgColors[card.status] || bgColors.active}">
                    <div class="absolute top-0 right-0 w-24 h-24 -mt-6 -mr-6 rounded-full bg-white/10"></div>
                    <div class="relative z-10 flex flex-col justify-between h-full">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-white">AmBilet</span>
                        </div>
                        <div class="text-3xl font-bold text-white">${card.amount} <span class="text-base font-medium opacity-80">RON</span></div>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-xs tracking-wider uppercase text-muted">Cod card</div>
                            <div class="px-3 py-1.5 mt-1 font-mono text-sm font-bold rounded-lg bg-surface text-secondary">${card.code}</div>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusClasses[card.status] || statusClasses.active}">
                            ${statusLabels[card.status] || 'Activ'}
                        </span>
                    </div>
                    ${card.recipient ? `
                    <div class="flex items-center gap-3 p-3 mb-4 rounded-xl bg-surface">
                        <div class="flex items-center justify-center rounded-full w-9 h-9 bg-border">
                            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold truncate text-secondary">${card.recipient.name}</div>
                            <div class="text-xs truncate text-muted">${card.recipient.email}</div>
                        </div>
                    </div>
                    ` : ''}
                    ${card.balance !== undefined && card.status === 'active' ? `
                    <div class="flex items-center justify-between p-4 mb-4 rounded-xl bg-primary/5">
                        <div>
                            <div class="text-xs text-muted">Balanta ramasa</div>
                            <div class="text-xl font-bold text-primary">${card.balance} RON</div>
                            <div class="text-xs text-muted">din ${card.amount} RON</div>
                        </div>
                        <div class="relative w-16 h-16">
                            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#E2E8F0" stroke-width="3"/>
                                <circle cx="18" cy="18" r="16" fill="none" stroke="currentColor" class="text-primary" stroke-width="3" stroke-dasharray="${(card.balance / card.amount) * 100} 100" stroke-linecap="round"/>
                            </svg>
                            <span class="absolute text-xs font-bold -translate-x-1/2 -translate-y-1/2 top-1/2 left-1/2 text-primary">${Math.round((card.balance / card.amount) * 100)}%</span>
                        </div>
                    </div>
                    ` : ''}
                    <div class="flex justify-between text-sm">
                        <div>
                            <div class="text-xs tracking-wider uppercase text-muted">Cumparat</div>
                            <div class="font-medium text-secondary">${card.purchased_at}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs tracking-wider uppercase text-muted">Expira</div>
                            <div class="font-medium text-secondary">${card.expires_at}</div>
                        </div>
                    </div>
                    ${card.status === 'active' && !card.recipient ? `
                    <div class="pt-4 mt-4 border-t border-border">
                        <button onclick="GiftCardsPage.useCard('${card.code}')" class="w-full btn btn-primary">Foloseste acum</button>
                    </div>
                    ` : ''}
                    ${card.status === 'pending' ? `
                    <div class="flex gap-2 pt-4 mt-4 border-t border-border">
                        <button onclick="GiftCardsPage.resendEmail('${card.code}')" class="flex-1 btn btn-secondary">Retrimite email</button>
                        <button onclick="GiftCardsPage.copyCode('${card.code}')" class="flex-1 btn btn-secondary">Copiaza codul</button>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    },

    renderReceivedCard(card) {
        const isActive = card.status === 'active';
        const isPending = card.status === 'pending';

        return `
            <div class="overflow-hidden transition-shadow bg-white border rounded-2xl border-border hover:shadow-lg">
                <div class="relative h-32 p-5 bg-gradient-to-br ${isActive ? 'from-green-600 to-green-700' : isPending ? 'from-amber-500 to-amber-600' : 'from-gray-500 to-gray-600'}">
                    <div class="absolute top-0 right-0 w-24 h-24 -mt-6 -mr-6 rounded-full bg-white/10"></div>
                    <div class="relative z-10 flex flex-col justify-between h-full">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-white">AmBilet</span>
                        </div>
                        <div class="text-3xl font-bold text-white">${card.amount} <span class="text-base font-medium opacity-80">RON</span></div>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-xs tracking-wider uppercase text-muted">Cod card</div>
                            <div class="px-3 py-1.5 mt-1 font-mono text-sm font-bold rounded-lg bg-surface text-secondary">${card.code}</div>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full ${isActive ? 'bg-green-100 text-green-700' : isPending ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'}">
                            ${isActive ? 'Activat' : isPending ? 'Neactivat' : 'Folosit'}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 p-3 mb-4 rounded-xl ${isActive ? 'bg-green-50' : 'bg-amber-50'}">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full ${isActive ? 'bg-green-100' : 'bg-amber-100'}">
                            <svg class="w-4 h-4 ${isActive ? 'text-green-600' : 'text-amber-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-muted">Cadou de la</div>
                            <div class="text-sm font-semibold text-secondary">${card.sender_name}</div>
                        </div>
                    </div>
                    ${card.message ? `
                    <div class="p-3 mb-4 border-l-4 border-yellow-400 rounded-r-lg bg-yellow-50">
                        <div class="text-xs font-semibold text-amber-700">Mesaj</div>
                        <div class="text-sm italic text-amber-900">"${card.message}"</div>
                    </div>
                    ` : ''}
                    ${isActive && card.balance !== undefined ? `
                    <div class="flex items-center justify-between p-4 mb-4 rounded-xl bg-green-50">
                        <div>
                            <div class="text-xs text-muted">Balanta disponibila</div>
                            <div class="text-xl font-bold text-green-600">${card.balance} RON</div>
                            <div class="text-xs text-muted">din ${card.amount} RON</div>
                        </div>
                        <div class="relative w-16 h-16">
                            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#E2E8F0" stroke-width="3"/>
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#059669" stroke-width="3" stroke-dasharray="${(card.balance / card.amount) * 100} 100" stroke-linecap="round"/>
                            </svg>
                            <span class="absolute text-xs font-bold text-green-600 -translate-x-1/2 -translate-y-1/2 top-1/2 left-1/2">${Math.round((card.balance / card.amount) * 100)}%</span>
                        </div>
                    </div>
                    ` : ''}
                    <div class="flex justify-between text-sm">
                        <div>
                            <div class="text-xs tracking-wider uppercase text-muted">Primit pe</div>
                            <div class="font-medium text-secondary">${card.received_at}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs tracking-wider uppercase text-muted">${isPending ? 'Activeaza pana la' : 'Expira'}</div>
                            <div class="font-medium ${isPending ? 'text-amber-600' : 'text-secondary'}">${card.expires_at}</div>
                        </div>
                    </div>
                    ${isActive ? `
                    <div class="flex gap-2 pt-4 mt-4 border-t border-border">
                        <button onclick="GiftCardsPage.useCard('${card.code}')" class="flex-1 btn btn-primary">Foloseste acum</button>
                        <button onclick="GiftCardsPage.viewTransactions('${card.code}')" class="flex-1 btn btn-secondary">Vezi tranzactii</button>
                    </div>
                    ` : isPending ? `
                    <div class="pt-4 mt-4 border-t border-border">
                        <button onclick="GiftCardsPage.activateCard('${card.code}')" class="w-full btn btn-primary">Activeaza cardul</button>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    },

    showEmptyStates() {
        document.getElementById('empty-purchased').classList.remove('hidden');
        document.getElementById('empty-received').classList.remove('hidden');
    },

    async useCard(code) {
        AmbiletNotifications.info('Functie in dezvoltare. Codul cardului: ' + code);
    },

    async resendEmail(code) {
        try {
            const response = await AmbiletAPI.post('/customer/gift-cards/resend', { code });
            if (response.success) {
                AmbiletNotifications.success('Email-ul a fost retrimis!');
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la retrimiterea email-ului.');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la retrimiterea email-ului.');
        }
    },

    copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            AmbiletNotifications.success('Codul a fost copiat!');
        }).catch(() => {
            AmbiletNotifications.error('Nu s-a putut copia codul.');
        });
    },

    async activateCard(code) {
        try {
            const response = await AmbiletAPI.post('/customer/gift-cards/activate', { code });
            if (response.success) {
                AmbiletNotifications.success('Cardul a fost activat!');
                this.loadGiftCards();
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la activarea cardului.');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la activarea cardului.');
        }
    },

    viewTransactions(code) {
        AmbiletNotifications.info('Functie in dezvoltare.');
    }
};

document.addEventListener('DOMContentLoaded', () => GiftCardsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
