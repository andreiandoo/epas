<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Finante';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'finance';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Finante</h1>
                    <p class="text-sm text-muted">Gestioneaza balanta si platile tale</p>
                </div>
            </div>


            <div class="grid gap-6 mb-8 lg:grid-cols-3">
                <div class="p-6 text-white bg-gradient-to-br from-primary to-primary-dark rounded-2xl">
                    <div class="flex items-center gap-3 mb-4"><div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-xl"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><p class="text-sm text-white/80">Balanta Disponibila</p><p class="text-3xl font-bold" id="available-balance">0 RON</p></div></div>
                    <p class="text-sm text-white/70">Suma disponibila pentru retragere</p>
                </div>
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-3 mb-4"><div class="flex items-center justify-center w-12 h-12 bg-warning/10 rounded-xl"><svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><p class="text-sm text-muted">In Procesare</p><p class="text-3xl font-bold text-secondary" id="pending-balance">0 RON</p></div></div>
                    <p class="text-sm text-muted">Plati in curs de procesare</p>
                </div>
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-3 mb-4"><div class="flex items-center justify-center w-12 h-12 bg-success/10 rounded-xl"><svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div><div><p class="text-sm text-muted">Total Incasat</p><p class="text-3xl font-bold text-secondary" id="total-paid-out">0 RON</p></div></div>
                    <p class="text-sm text-muted">Suma totala retrasa</p>
                </div>
            </div>

            <!-- Events with Balances -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold text-secondary">Sold per eveniment</h2>
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-surface">
                                <tr>
                                    <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Eveniment</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Venituri brute</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Comision</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Venituri nete</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Retras</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Sold disponibil</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-center text-secondary">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody id="events-list" class="divide-y divide-border">
                                <tr><td colspan="7" class="px-6 py-12 text-center text-muted">Se incarca...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 mb-6 border-b border-border">
                <button onclick="setTab('transactions')" class="px-6 py-3 text-sm font-medium border-b-2 tab-btn active border-primary text-primary" data-tab="transactions">Tranzactii</button>
                <button onclick="setTab('payouts')" class="px-6 py-3 text-sm font-medium border-b-2 border-transparent tab-btn text-muted hover:text-secondary" data-tab="payouts">Plati Primite</button>
            </div>

            <div id="transactions-tab" class="tab-content">
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="divide-y divide-border" id="transactions-list"></div>
                </div>
            </div>

            <div id="payouts-tab" class="hidden tab-content">
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <table class="w-full">
                        <thead class="bg-surface"><tr><th class="px-6 py-4 text-sm font-semibold text-left text-secondary">ID Plata</th><th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Suma</th><th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Status</th><th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Data</th></tr></thead>
                        <tbody id="payouts-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="payout-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black/50">
        <div class="w-full max-w-md p-6 bg-white rounded-2xl">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Solicita Plata</h3><button onclick="closePayoutModal()" class="text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="submitPayoutRequest(event)">
                <input type="hidden" id="payout-event-id" value="">
                <div id="payout-event-info" class="p-4 mb-4 bg-surface rounded-xl hidden">
                    <p class="mb-1 text-sm text-muted">Eveniment</p>
                    <p class="font-semibold text-secondary" id="payout-event-name"></p>
                </div>
                <div class="p-4 mb-6 bg-surface rounded-xl"><p class="mb-1 text-sm text-muted">Suma disponibila</p><p class="text-2xl font-bold text-secondary" id="modal-available-balance">0 RON</p></div>
                <div class="mb-4"><label class="label">Suma de retras</label><input type="number" id="payout-amount" min="100" step="0.01" class="w-full input" required><p class="mt-1 text-sm text-muted" id="payout-amount-hint">Suma minima: 100 RON</p></div>
                <div class="mb-4"><label class="label">Cont Bancar</label><select id="payout-account" class="w-full input" required><option value="">Se incarca...</option></select></div>
                <div class="mb-6"><label class="label">Note (optional)</label><textarea id="payout-notes" class="w-full input" rows="2" placeholder="Adauga note sau detalii..."></textarea></div>
                <div class="flex gap-3"><button type="button" onclick="closePayoutModal()" class="flex-1 btn btn-secondary">Anuleaza</button><button type="submit" class="flex-1 btn btn-primary">Solicita Plata</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let financeData = null;

document.addEventListener('DOMContentLoaded', function() { loadFinanceData(); });

async function loadFinanceData() {
    try {
        const response = await AmbiletAPI.get('/organizer/finance');
        if (response.success) {
            financeData = response.data;
            document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(financeData.available_balance || 0);
            document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(financeData.pending_balance || 0);
            document.getElementById('total-paid-out').textContent = AmbiletUtils.formatCurrency(financeData.total_paid_out || 0);
            renderEvents(financeData.events || []);
            renderTransactions(financeData.transactions || []);
            renderPayouts(financeData.payouts || []);
        } else { showEmptyFinance(); }
    } catch (error) { console.error(error); showEmptyFinance(); }
}

function showEmptyFinance() {
    document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('total-paid-out').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('events-list').innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-muted">Nu exista evenimente</td></tr>';
    document.getElementById('transactions-list').innerHTML = '<div class="p-6 text-center text-muted">Nu exista tranzactii momentan</div>';
    document.getElementById('payouts-list').innerHTML = '<tr><td colspan="4" class="px-6 py-12 text-center text-muted">Nu exista plati momentan</td></tr>';
}

function renderEvents(events) {
    const tbody = document.getElementById('events-list');
    if (!events.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-muted">Nu exista evenimente</td></tr>';
        return;
    }

    tbody.innerHTML = events.map(e => {
        const commissionModeIcon = e.commission_mode === 'added_on_top'
            ? '<span class="inline-flex items-center justify-center w-4 h-4 text-amber-500" title="Comision adaugat la pret"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"/></svg></span>'
            : '<span class="inline-flex items-center justify-center w-4 h-4 text-green-500" title="Comision inclus in pret"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>';

        const statusBadge = e.is_past
            ? '<span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">Incheiat</span>'
            : '<span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">Activ</span>';

        const canRequestPayout = e.available_balance >= 100;
        const payoutButton = canRequestPayout
            ? `<button onclick="openPayoutModal(${e.id}, '${e.title.replace(/'/g, "\\'")}', ${e.available_balance})" class="px-3 py-1.5 text-xs font-medium text-white bg-primary rounded-lg hover:bg-primary-dark">Solicita plata</button>`
            : `<span class="text-xs text-muted">Min. 100 RON</span>`;

        return `
            <tr class="hover:bg-surface/50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-surface overflow-hidden flex-shrink-0">
                            ${e.image ? `<img src="${e.image}" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center text-muted"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>'}
                        </div>
                        <div>
                            <p class="font-medium text-secondary">${e.title}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                ${statusBadge}
                                <span class="text-xs text-muted">${e.tickets_sold || 0} bilete</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-right font-medium">${AmbiletUtils.formatCurrency(e.gross_revenue)}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <span class="text-amber-600">${AmbiletUtils.formatCurrency(e.commission_amount)}</span>
                        <span class="text-xs text-muted">(${e.commission_rate}%)</span>
                        ${commissionModeIcon}
                    </div>
                </td>
                <td class="px-6 py-4 text-right font-semibold text-success">${AmbiletUtils.formatCurrency(e.net_revenue)}</td>
                <td class="px-6 py-4 text-right text-muted">${AmbiletUtils.formatCurrency(e.total_paid_out)}</td>
                <td class="px-6 py-4 text-right">
                    <span class="font-semibold ${e.available_balance > 0 ? 'text-primary' : 'text-muted'}">${AmbiletUtils.formatCurrency(e.available_balance)}</span>
                    ${e.pending_payout > 0 ? `<br><span class="text-xs text-warning">In procesare: ${AmbiletUtils.formatCurrency(e.pending_payout)}</span>` : ''}
                </td>
                <td class="px-6 py-4 text-center">${payoutButton}</td>
            </tr>
        `;
    }).join('');
}

function renderTransactions(transactions) {
    if (!transactions.length) { document.getElementById('transactions-list').innerHTML = '<div class="p-6 text-center text-muted">Nu exista tranzactii momentan</div>'; return; }
    document.getElementById('transactions-list').innerHTML = transactions.map(t => `
        <div class="flex items-center justify-between p-4 hover:bg-surface/50">
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
    if (!payouts.length) { document.getElementById('payouts-list').innerHTML = '<tr><td colspan="4" class="px-6 py-12 text-center text-muted">Nu exista plati momentan</td></tr>'; return; }
    document.getElementById('payouts-list').innerHTML = payouts.map(p => {
        const statusColors = {
            'pending': { class: 'bg-warning/10 text-warning', label: 'In asteptare' },
            'approved': { class: 'bg-blue-100 text-blue-600', label: 'Aprobata' },
            'processing': { class: 'bg-blue-100 text-blue-600', label: 'In procesare' },
            'completed': { class: 'bg-success/10 text-success', label: 'Finalizata' },
            'rejected': { class: 'bg-error/10 text-error', label: 'Respinsa' },
            'cancelled': { class: 'bg-gray-100 text-gray-600', label: 'Anulata' }
        };
        const statusInfo = statusColors[p.status] || statusColors['pending'];
        const rejectionTooltip = p.status === 'rejected' && p.rejection_reason
            ? `<span class="ml-1 relative group cursor-help">
                <svg class="w-4 h-4 inline text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap max-w-xs z-50">
                    <span class="font-semibold">Motiv:</span> ${p.rejection_reason}
                </span>
               </span>`
            : '';
        return `
            <tr class="hover:bg-surface/50">
                <td class="px-6 py-4 font-medium text-secondary">${p.reference || '#' + p.id}</td>
                <td class="px-6 py-4 font-semibold">${AmbiletUtils.formatCurrency(p.amount)}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 ${statusInfo.class} text-sm rounded-full">${statusInfo.label}</span>
                    ${rejectionTooltip}
                </td>
                <td class="px-6 py-4 text-muted">${AmbiletUtils.formatDate(p.created_at)}</td>
            </tr>
        `;
    }).join('');
}

function setTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => { btn.classList.remove('active', 'border-primary', 'text-primary'); btn.classList.add('border-transparent', 'text-muted'); });
    document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active', 'border-primary', 'text-primary');
    document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.remove('border-transparent', 'text-muted');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
}

let currentPayoutMaxAmount = 0;

async function openPayoutModal(eventId, eventName, availableBalance) {
    currentPayoutMaxAmount = availableBalance;
    document.getElementById('payout-event-id').value = eventId;
    document.getElementById('payout-event-name').textContent = eventName;
    document.getElementById('payout-event-info').classList.remove('hidden');
    document.getElementById('modal-available-balance').textContent = AmbiletUtils.formatCurrency(availableBalance);
    document.getElementById('payout-amount').value = '';
    document.getElementById('payout-amount').max = availableBalance;
    document.getElementById('payout-amount-hint').textContent = `Suma minima: 100 RON, maxima: ${AmbiletUtils.formatCurrency(availableBalance)}`;
    document.getElementById('payout-notes').value = '';

    // Load bank accounts
    const select = document.getElementById('payout-account');
    select.innerHTML = '<option value="">Se incarca...</option>';
    try {
        const response = await AmbiletAPI.get('/organizer/bank-accounts');
        if (response.success && response.data) {
            const accounts = response.data.accounts || response.data || [];
            select.innerHTML = '<option value="">Selecteaza contul</option>';
            if (accounts.length === 0) {
                select.innerHTML = '<option value="">Nu ai conturi bancare adaugate. Adauga unul in Setari.</option>';
            } else {
                accounts.forEach(acc => {
                    const label = (acc.bank_name || 'Cont') + ' - ****' + (acc.iban ? acc.iban.slice(-4) : acc.account_number?.slice(-4) || '');
                    select.innerHTML += `<option value="${acc.id}">${label}</option>`;
                });
            }
        }
    } catch (error) {
        console.error('Failed to load bank accounts:', error);
        select.innerHTML = '<option value="">Eroare la incarcarea conturilor</option>';
    }

    document.getElementById('payout-modal').classList.remove('hidden');
    document.getElementById('payout-modal').classList.add('flex');

    // Add input watcher for amount
    const amountInput = document.getElementById('payout-amount');
    amountInput.oninput = function() {
        const val = parseFloat(this.value) || 0;
        if (val > currentPayoutMaxAmount) {
            this.value = currentPayoutMaxAmount;
        }
    };
}

function closePayoutModal() {
    document.getElementById('payout-modal').classList.add('hidden');
    document.getElementById('payout-modal').classList.remove('flex');
}

async function submitPayoutRequest(e) {
    e.preventDefault();
    const eventId = document.getElementById('payout-event-id').value;
    const amount = parseFloat(document.getElementById('payout-amount').value);
    const notes = document.getElementById('payout-notes').value;
    const accountId = document.getElementById('payout-account').value;

    if (!accountId) {
        AmbiletNotifications.error('Te rugam sa selectezi un cont bancar');
        return;
    }
    if (amount < 100) {
        AmbiletNotifications.error('Suma minima este 100 RON');
        return;
    }
    if (amount > currentPayoutMaxAmount) {
        AmbiletNotifications.error('Suma depaseste soldul disponibil');
        return;
    }

    try {
        const response = await AmbiletAPI.post('/organizer/payouts', {
            amount: amount,
            event_id: eventId || null,
            bank_account_id: accountId,
            notes: notes || null
        });

        if (response.success) {
            AmbiletNotifications.success('Cererea de plata a fost trimisa cu succes!');
            closePayoutModal();
            loadFinanceData(); // Refresh data
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la trimiterea cererii de plata');
        }
    } catch (error) {
        console.error('Payout request failed:', error);
        AmbiletNotifications.error('Eroare la trimiterea cererii de plata');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
