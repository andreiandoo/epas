<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Finante';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'finance';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Finante</h1>
                    <p class="text-sm text-muted">Gestioneaza balanta si platile tale</p>
                </div>
                <button onclick="requestPayout()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Solicita Plata</button>
            </div>


            <div class="grid lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white">
                    <div class="flex items-center gap-3 mb-4"><div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><p class="text-white/80 text-sm">Balanta Disponibila</p><p class="text-3xl font-bold" id="available-balance">0 RON</p></div></div>
                    <p class="text-sm text-white/70">Suma disponibila pentru retragere</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-4"><div class="w-12 h-12 bg-warning/10 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><p class="text-muted text-sm">In Procesare</p><p class="text-3xl font-bold text-secondary" id="pending-balance">0 RON</p></div></div>
                    <p class="text-sm text-muted">Vanzari in asteptare (7 zile)</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-4"><div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div><div><p class="text-muted text-sm">Total Incasat</p><p class="text-3xl font-bold text-secondary" id="total-earned">0 RON</p></div></div>
                    <p class="text-sm text-muted">Din toate evenimentele</p>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mb-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <div><h3 class="font-semibold text-secondary mb-1">Informatii Comisioane</h3><p class="text-sm text-muted">Comisionul <?= SITE_NAME ?> este de <span class="font-semibold text-primary" id="commission-rate-display">--%</span> din valoarea fiecarui bilet vandut. <span id="commission-mode-display"></span></p></div>
                </div>
            </div>

            <div class="flex items-center gap-2 mb-6 border-b border-border">
                <button onclick="setTab('transactions')" class="tab-btn active px-6 py-3 text-sm font-medium border-b-2 border-primary text-primary" data-tab="transactions">Tranzactii</button>
                <button onclick="setTab('payouts')" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-transparent text-muted hover:text-secondary" data-tab="payouts">Plati Primite</button>
            </div>

            <div id="transactions-tab" class="tab-content">
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <div class="divide-y divide-border" id="transactions-list"></div>
                </div>
            </div>

            <div id="payouts-tab" class="tab-content hidden">
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-surface"><tr><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">ID Plata</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Suma</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Cont</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data</th></tr></thead>
                        <tbody id="payouts-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="payout-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Solicita Plata</h3><button onclick="closePayoutModal()" class="text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="submitPayoutRequest(event)">
                <div class="bg-surface rounded-xl p-4 mb-6"><p class="text-sm text-muted mb-1">Suma disponibila</p><p class="text-2xl font-bold text-secondary" id="modal-available-balance">0 RON</p></div>
                <div class="mb-4"><label class="label">Suma de retras</label><input type="number" id="payout-amount" min="100" class="input w-full" required><p class="text-sm text-muted mt-1">Suma minima: 100 RON</p></div>
                <div class="mb-6"><label class="label">Cont Bancar</label><select id="payout-account" class="input w-full" required><option value="">Selecteaza contul</option><option value="1">ING Bank - ****3456</option><option value="2">BRD - ****4321</option></select></div>
                <div class="flex gap-3"><button type="button" onclick="closePayoutModal()" class="btn btn-secondary flex-1">Anuleaza</button><button type="submit" class="btn btn-primary flex-1">Solicita Plata</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let availableBalance = 0;

document.addEventListener('DOMContentLoaded', function() { loadFinanceData(); loadCommissionInfo(); });

async function loadCommissionInfo() {
    // Try to get from localStorage first
    const orgData = JSON.parse(localStorage.getItem('ambilet_organizer_data') || 'null');
    if (orgData && orgData.commission_rate !== undefined) {
        updateCommissionDisplay(orgData.commission_rate, orgData.commission_mode);
    } else {
        // Fetch from API
        try {
            const response = await AmbiletAPI.get('/organizer/contract');
            if (response.success) {
                updateCommissionDisplay(response.data.commission_rate, response.data.commission_mode);
            }
        } catch (error) { /* Use default */ }
    }
}

function updateCommissionDisplay(rate, mode) {
    document.getElementById('commission-rate-display').textContent = rate + '%';
    const modeText = mode === 'added_on_top' ? 'Acest comision se adauga la pretul biletului.' : 'Acest comision este inclus in pretul biletului.';
    document.getElementById('commission-mode-display').textContent = modeText;
}

async function loadFinanceData() {
    try {
        const response = await AmbiletAPI.get('/organizer/finance');
        if (response.success) {
            const data = response.data;
            availableBalance = data.available_balance || 0;
            document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(availableBalance);
            document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(data.pending_balance || 0);
            document.getElementById('total-earned').textContent = AmbiletUtils.formatCurrency(data.total_earned || 0);
            renderTransactions(data.transactions || []);
            renderPayouts(data.payouts || []);
        } else { showEmptyFinance(); }
    } catch (error) { showEmptyFinance(); }
}

function showEmptyFinance() {
    document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('total-earned').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('transactions-list').innerHTML = '<div class="p-6 text-center text-muted">Nu exista tranzactii momentan</div>';
    document.getElementById('payouts-list').innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-muted">Nu exista plati momentan</td></tr>';
}

function renderTransactions(transactions) {
    if (!transactions.length) { document.getElementById('transactions-list').innerHTML = '<div class="p-6 text-center text-muted">Nu exista tranzactii momentan</div>'; return; }
    document.getElementById('transactions-list').innerHTML = transactions.map(t => `
        <div class="p-4 flex items-center justify-between hover:bg-surface/50">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center ${t.type === 'sale' ? 'bg-success/10' : t.type === 'refund' ? 'bg-error/10' : 'bg-blue-100'}">
                    <svg class="w-5 h-5 ${t.type === 'sale' ? 'text-success' : t.type === 'refund' ? 'text-error' : 'text-blue-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${t.type === 'sale' ? 'M12 4v16m8-8H4' : t.type === 'refund' ? 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' : 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'}"/></svg>
                </div>
                <div><p class="font-medium text-secondary">${t.description}</p><p class="text-sm text-muted">${AmbiletUtils.formatDate(t.date)}</p></div>
            </div>
            <span class="font-semibold ${t.amount >= 0 ? 'text-success' : 'text-error'}">${t.amount >= 0 ? '+' : ''}${AmbiletUtils.formatCurrency(t.amount)}</span>
        </div>
    `).join('');
}

function renderPayouts(payouts) {
    if (!payouts.length) { document.getElementById('payouts-list').innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-muted">Nu exista plati momentan</td></tr>'; return; }
    document.getElementById('payouts-list').innerHTML = payouts.map(p => `
        <tr class="hover:bg-surface/50"><td class="px-6 py-4 font-medium text-secondary">${p.id}</td><td class="px-6 py-4 font-semibold">${AmbiletUtils.formatCurrency(p.amount)}</td><td class="px-6 py-4 text-muted">${p.account}</td><td class="px-6 py-4"><span class="px-3 py-1 bg-${p.status === 'completed' ? 'success' : 'warning'}/10 text-${p.status === 'completed' ? 'success' : 'warning'} text-sm rounded-full">${p.status === 'completed' ? 'Finalizata' : 'In procesare'}</span></td><td class="px-6 py-4 text-muted">${AmbiletUtils.formatDate(p.date)}</td></tr>
    `).join('');
}

function setTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => { btn.classList.remove('active', 'border-primary', 'text-primary'); btn.classList.add('border-transparent', 'text-muted'); });
    document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active', 'border-primary', 'text-primary');
    document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.remove('border-transparent', 'text-muted');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
}

function requestPayout() { document.getElementById('modal-available-balance').textContent = AmbiletUtils.formatCurrency(availableBalance); document.getElementById('payout-modal').classList.remove('hidden'); document.getElementById('payout-modal').classList.add('flex'); }
function closePayoutModal() { document.getElementById('payout-modal').classList.add('hidden'); document.getElementById('payout-modal').classList.remove('flex'); }
function submitPayoutRequest(e) { e.preventDefault(); const amount = parseFloat(document.getElementById('payout-amount').value); if (amount < 100) { AmbiletNotifications.error('Suma minima este 100 RON'); return; } if (amount > availableBalance) { AmbiletNotifications.error('Suma insuficienta disponibila'); return; } AmbiletNotifications.success('Cererea de plata a fost trimisa'); closePayoutModal(); }
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
