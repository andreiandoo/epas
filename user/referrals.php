<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Invita prieteni';
$currentPage = 'referrals';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Container with Sidebar -->
<div class="px-4 py-6 mx-auto max-w-7xl lg:py-8">
    <div class="flex flex-col gap-6 lg:flex-row">
        <!-- Sidebar -->
        <?php require_once dirname(__DIR__) . '/includes/user-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 lg:pt-24">
            <!-- Hero Section -->
            <div class="relative p-6 mb-6 overflow-hidden text-white lg:p-10 rounded-2xl bg-gradient-to-br from-secondary to-gray-900">
                <div class="absolute top-0 right-0 w-1/2 h-full opacity-30 bg-gradient-radial from-primary/50 to-transparent"></div>
                <div class="relative z-10 grid gap-8 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h1 class="mb-4 text-2xl font-extrabold lg:text-3xl">Invita prieteni,<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-red-400 to-yellow-400">castiga credit</span></h1>
                        <p class="mb-6 text-gray-400">Primesti 25 RON credit pentru fiecare prieten care isi face cont si cumpara primul bilet. Prietenul tau primeste 15 RON discount la prima comanda!</p>
                        <div class="inline-flex items-center gap-3 px-5 py-3 rounded-xl bg-white/10 backdrop-blur-sm">
                            <span class="text-3xl font-extrabold text-yellow-400">25 RON</span>
                            <span class="text-sm text-gray-400">credit pentru tine<strong class="block text-white">pentru fiecare invitatie</strong></span>
                        </div>
                    </div>
                    <div class="p-5 rounded-xl bg-white/5">
                        <div class="mb-3 text-xs font-semibold tracking-widest uppercase text-gray-400">Link-ul tau personal de invitatie</div>
                        <div class="flex gap-2 mb-4">
                            <input type="text" class="flex-1 px-4 py-3 font-mono text-sm text-white border rounded-lg bg-white/10 border-white/20" id="referral-link" value="---" readonly>
                            <button class="flex items-center gap-2 px-5 py-3 font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-primary/80 hover:shadow-lg hover:-translate-y-0.5" id="copy-btn">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Copiaza
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button class="flex items-center justify-center flex-1 gap-2 px-4 py-3 text-sm font-medium text-white transition-colors border rounded-lg border-white/20 hover:bg-blue-600 hover:border-blue-600" onclick="shareOnFacebook()">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook
                            </button>
                            <button class="flex items-center justify-center flex-1 gap-2 px-4 py-3 text-sm font-medium text-white transition-colors border rounded-lg border-white/20 hover:bg-green-600 hover:border-green-600" onclick="shareOnWhatsApp()">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </button>
                            <button class="flex items-center justify-center flex-1 gap-2 px-4 py-3 text-sm font-medium text-white transition-colors border rounded-lg border-white/20 hover:bg-red-600 hover:border-red-600" onclick="shareByEmail()">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
                <div class="p-5 text-center bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div class="text-2xl font-extrabold text-secondary" id="stat-invited">0</div>
                    <div class="text-sm text-muted">Prieteni invitati</div>
                </div>
                <div class="p-5 text-center bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 rounded-full bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-2xl font-extrabold text-secondary" id="stat-completed">0</div>
                    <div class="text-sm text-muted">Au cumparat bilete</div>
                </div>
                <div class="p-5 text-center bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 rounded-full bg-yellow-100">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-2xl font-extrabold text-secondary" id="stat-pending">0</div>
                    <div class="text-sm text-muted">In asteptare</div>
                </div>
                <div class="p-5 text-center bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 rounded-full bg-red-100">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-2xl font-extrabold text-secondary" id="stat-credit">0 RON</div>
                    <div class="text-sm text-muted">Credit castigat</div>
                </div>
            </div>

            <!-- How it Works -->
            <h2 class="mb-4 text-lg font-bold text-secondary">Cum functioneaza</h2>
            <div class="grid gap-6 mb-6 md:grid-cols-3">
                <div class="relative p-6 pt-8 text-center bg-white border rounded-xl border-border">
                    <div class="absolute flex items-center justify-center w-8 h-8 text-sm font-bold text-white -translate-x-1/2 rounded-full -top-3 left-1/2 bg-gradient-to-r from-primary to-primary/80">1</div>
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-xl bg-surface">
                        <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </div>
                    <h3 class="mb-2 font-semibold text-secondary">Distribuie link-ul</h3>
                    <p class="text-sm text-muted">Trimite link-ul tau personal prietenilor prin orice metoda preferi</p>
                </div>
                <div class="relative p-6 pt-8 text-center bg-white border rounded-xl border-border">
                    <div class="absolute flex items-center justify-center w-8 h-8 text-sm font-bold text-white -translate-x-1/2 rounded-full -top-3 left-1/2 bg-gradient-to-r from-primary to-primary/80">2</div>
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-xl bg-surface">
                        <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="mb-2 font-semibold text-secondary">Prietenul cumpara</h3>
                    <p class="text-sm text-muted">Prietenul tau isi face cont si cumpara primul bilet cu 15 RON discount</p>
                </div>
                <div class="relative p-6 pt-8 text-center bg-white border rounded-xl border-border">
                    <div class="absolute flex items-center justify-center w-8 h-8 text-sm font-bold text-white -translate-x-1/2 rounded-full -top-3 left-1/2 bg-gradient-to-r from-primary to-primary/80">3</div>
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-xl bg-surface">
                        <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="mb-2 font-semibold text-secondary">Primesti credit</h3>
                    <p class="text-sm text-muted">25 RON credit se adauga automat in contul tau pentru urmatoarea comanda</p>
                </div>
            </div>

            <!-- Referrals List -->
            <div class="mb-6 overflow-hidden bg-white border rounded-2xl border-border">
                <div class="p-5 border-b border-border">
                    <h2 class="font-semibold text-secondary">Prietenii invitati</h2>
                </div>
                <div id="referrals-list">
                    <!-- Referrals will be loaded here -->
                </div>
                <div class="hidden p-10 text-center" id="empty-referrals">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 rounded-full bg-surface">
                        <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <h3 class="mb-2 font-semibold text-secondary">Nu ai invitat inca pe nimeni</h3>
                    <p class="text-muted">Distribuie link-ul tau pentru a incepe sa castigi credit.</p>
                </div>
            </div>

            <!-- Credit History -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="p-5 border-b border-border">
                    <h2 class="font-semibold text-secondary">Istoricul creditului</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full" id="credit-history-table">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Tranzactie</th>
                                <th class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Data</th>
                                <th class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Suma</th>
                                <th class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Balanta</th>
                            </tr>
                        </thead>
                        <tbody id="credit-history-body">
                            <!-- History will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div class="hidden p-10 text-center" id="empty-history">
                    <p class="text-muted">Nu ai inca tranzactii in istoric.</p>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const ReferralsPage = {
    referralLink: '',

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/invitatii';
            return;
        }

        this.loadReferralData();
        this.setupCopyButton();
    },

    async loadReferralData() {
        try {
            const response = await AmbiletAPI.get('/customer/referrals');
            if (response.success) {
                this.referralLink = response.referral_link || 'ambilet.ro/r/' + (AmbiletAuth.getUser()?.referral_code || 'CODE');
                document.getElementById('referral-link').value = this.referralLink;
                this.updateStats(response.stats || {});
                this.renderReferrals(response.referrals || []);
                this.renderHistory(response.history || []);
            }
        } catch (error) {
            console.error('Error loading referral data:', error);
            const user = AmbiletAuth.getUser();
            document.getElementById('referral-link').value = 'ambilet.ro/r/' + (user?.referral_code || 'CODE');
        }
    },

    updateStats(stats) {
        document.getElementById('stat-invited').textContent = stats.invited || '0';
        document.getElementById('stat-completed').textContent = stats.completed || '0';
        document.getElementById('stat-pending').textContent = stats.pending || '0';
        document.getElementById('stat-credit').textContent = (stats.credit || 0) + ' RON';
    },

    renderReferrals(referrals) {
        const container = document.getElementById('referrals-list');
        const emptyState = document.getElementById('empty-referrals');

        if (referrals.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.innerHTML = referrals.map(ref => `
            <div class="flex flex-wrap items-center gap-4 p-5 transition-colors border-b border-surface last:border-0 hover:bg-surface/50">
                <div class="flex items-center justify-center flex-shrink-0 w-11 h-11 font-semibold text-white rounded-full bg-gradient-to-br from-purple-500 to-pink-500">${ref.initials}</div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-secondary">${ref.name}</div>
                    <div class="text-sm text-muted">${ref.email}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-muted">Inregistrat</div>
                    <div class="text-sm font-medium text-secondary">${ref.date}</div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full ${ref.completed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                    ${ref.completed ? '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'}
                    ${ref.completed ? 'Completat' : 'In asteptare'}
                </span>
                <div class="text-right min-w-[80px]">
                    <div class="font-bold ${ref.completed ? 'text-green-600' : 'text-gray-400'}">${ref.completed ? '+25 RON' : '—'}</div>
                    <div class="text-xs text-muted">${ref.completed ? 'credit primit' : 'nu a cumparat'}</div>
                </div>
            </div>
        `).join('');
    },

    renderHistory(history) {
        const tbody = document.getElementById('credit-history-body');
        const emptyState = document.getElementById('empty-history');
        const table = document.getElementById('credit-history-table');

        if (history.length === 0) {
            table.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        table.classList.remove('hidden');
        tbody.innerHTML = history.map(item => `
            <tr class="border-b border-surface last:border-0">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg ${item.type === 'credit' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${item.type === 'credit' ? 'M12 6v6m0 0v6m0-6h6m-6 0H6' : 'M20 12H4'}"/></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-secondary">${item.title}</div>
                            <div class="text-sm text-muted">${item.description}</div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-muted">${item.date}</td>
                <td class="px-5 py-4 font-semibold ${item.type === 'credit' ? 'text-green-600' : 'text-muted'}">${item.type === 'credit' ? '+' : '-'}${item.amount} RON</td>
                <td class="px-5 py-4 font-semibold text-secondary">${item.balance} RON</td>
            </tr>
        `).join('');
    },

    setupCopyButton() {
        document.getElementById('copy-btn').addEventListener('click', () => {
            const input = document.getElementById('referral-link');
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = document.getElementById('copy-btn');
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copiat!';
                setTimeout(() => {
                    btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copiaza';
                }, 2000);
                AmbiletNotifications.success('Link copiat!');
            }).catch(() => {
                input.select();
                document.execCommand('copy');
                AmbiletNotifications.success('Link copiat!');
            });
        });
    }
};

function shareOnFacebook() {
    const link = document.getElementById('referral-link').value;
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent('https://' + link), '_blank', 'width=600,height=400');
}

function shareOnWhatsApp() {
    const link = document.getElementById('referral-link').value;
    const text = 'Inregistreaza-te pe AmBilet folosind link-ul meu si primesti 15 RON discount la prima comanda! ' + 'https://' + link;
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}

function shareByEmail() {
    const link = document.getElementById('referral-link').value;
    const subject = 'Ti-am trimis 15 RON pentru bilete pe AmBilet!';
    const body = 'Salut!\n\nInregistreaza-te pe AmBilet folosind link-ul meu si primesti 15 RON discount la prima comanda!\n\nhttps://' + link + '\n\nNe vedem la concert!';
    window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

document.addEventListener('DOMContentLoaded', () => ReferralsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
