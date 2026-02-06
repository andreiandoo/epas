<?php
/**
 * TICS Organizer Finance - Balance and Payouts Management
 * Manage balance and request payouts
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Demo finance data
$financeData = [
    'available_balance' => 245800,
    'pending_balance' => 48500,
    'total_paid_out' => 1850000,
];

// Demo events with balances
$events = [
    [
        'id' => 'coldplay-2026',
        'name' => 'Coldplay: Music of the Spheres',
        'date' => '2026-02-14',
        'status' => 'active',
        'tickets_sold' => 18432,
        'gross_revenue' => 2847650,
        'commission_rate' => 5,
        'commission_amount' => 142382,
        'net_revenue' => 2705268,
        'paid_out' => 2500000,
        'available_balance' => 205268,
        'pending_payout' => 48500,
        'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=100&h=100&fit=crop',
    ],
    [
        'id' => 'untold-2025',
        'name' => 'UNTOLD Festival 2025',
        'date' => '2025-08-07',
        'status' => 'ended',
        'tickets_sold' => 85000,
        'gross_revenue' => 8500000,
        'commission_rate' => 5,
        'commission_amount' => 425000,
        'net_revenue' => 8075000,
        'paid_out' => 8075000,
        'available_balance' => 0,
        'pending_payout' => 0,
        'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=100&h=100&fit=crop',
    ],
    [
        'id' => 'comedy-show',
        'name' => 'Stand-up Comedy Night',
        'date' => '2025-12-20',
        'status' => 'ended',
        'tickets_sold' => 2500,
        'gross_revenue' => 187500,
        'commission_rate' => 5,
        'commission_amount' => 9375,
        'net_revenue' => 178125,
        'paid_out' => 137593,
        'available_balance' => 40532,
        'pending_payout' => 0,
        'image' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=100&h=100&fit=crop',
    ],
];

// Demo transactions
$transactions = [
    ['id' => 1, 'date' => '2026-01-28', 'type' => 'sale', 'description' => 'Vanzare 4x General Admission', 'amount' => 596, 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 2, 'date' => '2026-01-27', 'type' => 'sale', 'description' => 'Vanzare 2x VIP Experience', 'amount' => 998, 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 3, 'date' => '2026-01-26', 'type' => 'refund', 'description' => 'Rambursare 1x Golden Circle', 'amount' => -349, 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 4, 'date' => '2026-01-25', 'type' => 'payout', 'description' => 'Plata catre cont bancar', 'amount' => -50000, 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 5, 'date' => '2026-01-24', 'type' => 'sale', 'description' => 'Vanzare 6x General Admission', 'amount' => 894, 'event' => 'Coldplay: Music of the Spheres'],
];

// Demo payouts
$payouts = [
    ['id' => 1, 'date' => '2026-01-20', 'amount' => 48500, 'status' => 'pending', 'account' => 'BCR ****4521', 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 2, 'date' => '2026-01-15', 'amount' => 100000, 'status' => 'completed', 'account' => 'BCR ****4521', 'event' => 'Coldplay: Music of the Spheres'],
    ['id' => 3, 'date' => '2025-12-28', 'amount' => 137593, 'status' => 'completed', 'account' => 'ING ****8872', 'event' => 'Stand-up Comedy Night'],
];

// Demo bank accounts
$bankAccounts = [
    ['id' => 1, 'bank' => 'BCR', 'iban' => 'RO49AAAA1B31007593840000', 'holder' => 'SC MUSIC EVENTS SRL'],
    ['id' => 2, 'bank' => 'ING', 'iban' => 'RO61INGB0000999900118872', 'holder' => 'SC MUSIC EVENTS SRL'],
];

// Current page for sidebar
$currentPage = 'finance';

// Page config for head
$pageTitle = 'Finante';
$pageDescription = 'Gestioneaza balanta si platile tale';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Finante</h1>
                            <p class="text-sm text-gray-500">Gestioneaza balanta si platile tale</p>
                        </div>
                        <button onclick="openPayoutModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Solicita plata
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Balance Cards -->
                <div class="grid lg:grid-cols-3 gap-6 mb-8">
                    <!-- Available Balance -->
                    <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-6 text-white">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-white/80 text-sm">Balanta disponibila</p>
                                <p class="text-3xl font-bold"><?= number_format($financeData['available_balance']) ?> RON</p>
                            </div>
                        </div>
                        <p class="text-white/70 text-sm">Suma disponibila pentru retragere</p>
                    </div>

                    <!-- Pending Balance -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">In procesare</p>
                                <p class="text-3xl font-bold text-gray-900"><?= number_format($financeData['pending_balance']) ?> RON</p>
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm">Plati in curs de procesare</p>
                    </div>

                    <!-- Total Paid Out -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Total incasat</p>
                                <p class="text-3xl font-bold text-gray-900"><?= number_format($financeData['total_paid_out']) ?> RON</p>
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm">Suma totala retrasa</p>
                    </div>
                </div>

                <!-- Events with Balances -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-8">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">Sold per eveniment</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Eveniment</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Venituri brute</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Comision</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Venituri nete</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Retras</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Sold disponibil</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($events as $event): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars($event['image']) ?>" class="w-10 h-10 rounded-lg object-cover" alt="">
                                            <div>
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars($event['name']) ?></p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="px-2 py-0.5 text-xs <?= $event['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?> rounded-full">
                                                        <?= $event['status'] === 'active' ? 'Activ' : 'Incheiat' ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500"><?= number_format($event['tickets_sold']) ?> bilete</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-gray-900"><?= number_format($event['gross_revenue']) ?> RON</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-amber-600"><?= number_format($event['commission_amount']) ?> RON</span>
                                        <span class="text-xs text-gray-400 ml-1">(<?= $event['commission_rate'] ?>%)</span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-green-600"><?= number_format($event['net_revenue']) ?> RON</td>
                                    <td class="px-6 py-4 text-right text-gray-500"><?= number_format($event['paid_out']) ?> RON</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-semibold <?= $event['available_balance'] > 0 ? 'text-indigo-600' : 'text-gray-400' ?>">
                                            <?= number_format($event['available_balance']) ?> RON
                                        </span>
                                        <?php if ($event['pending_payout'] > 0): ?>
                                        <br><span class="text-xs text-amber-600">In procesare: <?= number_format($event['pending_payout']) ?> RON</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($event['available_balance'] >= 100): ?>
                                        <button onclick="openPayoutModal('<?= $event['id'] ?>', '<?= addslashes($event['name']) ?>', <?= $event['available_balance'] ?>)" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                            Solicita plata
                                        </button>
                                        <?php else: ?>
                                        <span class="text-xs text-gray-400">Min. 100 RON</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Transactions & Payouts -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Recent Transactions -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-bold text-gray-900">Tranzactii recente</h2>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($transactions as $tx): ?>
                            <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center <?= $tx['type'] === 'sale' ? 'bg-green-100' : ($tx['type'] === 'refund' ? 'bg-red-100' : 'bg-blue-100') ?>">
                                        <?php if ($tx['type'] === 'sale'): ?>
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <?php elseif ($tx['type'] === 'refund'): ?>
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                        <?php else: ?>
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($tx['description']) ?></p>
                                        <p class="text-xs text-gray-500"><?= date('d M Y', strtotime($tx['date'])) ?></p>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold <?= $tx['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $tx['amount'] >= 0 ? '+' : '' ?><?= number_format($tx['amount']) ?> RON
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Payouts -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-bold text-gray-900">Plati recente</h2>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($payouts as $payout): ?>
                            <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= number_format($payout['amount']) ?> RON</p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($payout['account']) ?> - <?= date('d M Y', strtotime($payout['date'])) ?></p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $payout['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($payout['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') ?>">
                                    <?= $payout['status'] === 'completed' ? 'Finalizata' : ($payout['status'] === 'pending' ? 'In asteptare' : ucfirst($payout['status'])) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payout Modal -->
    <div id="payoutModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50" onclick="if(event.target === this) closePayoutModal()">
        <div class="w-full max-w-md bg-white rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Solicita plata</h3>
                <button onclick="closePayoutModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="submitPayout(event)">
                <input type="hidden" id="payoutEventId">

                <!-- Event Info (hidden when general payout) -->
                <div id="payoutEventInfo" class="hidden p-4 bg-gray-50 rounded-xl mb-4">
                    <p class="text-sm text-gray-500 mb-1">Eveniment</p>
                    <p class="font-semibold text-gray-900" id="payoutEventName"></p>
                </div>

                <div class="p-4 bg-indigo-50 rounded-xl mb-6">
                    <p class="text-sm text-gray-500 mb-1">Suma disponibila</p>
                    <p class="text-2xl font-bold text-indigo-600" id="modalAvailableBalance"><?= number_format($financeData['available_balance']) ?> RON</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Suma de retras</label>
                    <input type="number" id="payoutAmount" min="100" step="0.01" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required placeholder="0.00">
                    <p class="text-xs text-gray-500 mt-1" id="payoutAmountHint">Suma minima: 100 RON</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cont bancar</label>
                    <select id="payoutAccount" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" required>
                        <option value="">Selecteaza contul</option>
                        <?php foreach ($bankAccounts as $account): ?>
                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['bank']) ?> - ****<?= substr($account['iban'], -4) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Note (optional)</label>
                    <textarea id="payoutNotes" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none" rows="2" placeholder="Adauga note sau detalii..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closePayoutModal()" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">Anuleaza</button>
                    <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">Solicita plata</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentMaxAmount = <?= $financeData['available_balance'] ?>;

        function openPayoutModal(eventId = null, eventName = null, availableBalance = null) {
            const modal = document.getElementById('payoutModal');
            const eventInfo = document.getElementById('payoutEventInfo');

            if (eventId) {
                document.getElementById('payoutEventId').value = eventId;
                document.getElementById('payoutEventName').textContent = eventName;
                document.getElementById('modalAvailableBalance').textContent = formatCurrency(availableBalance);
                document.getElementById('payoutAmountHint').textContent = `Suma minima: 100 RON, maxima: ${formatCurrency(availableBalance)}`;
                currentMaxAmount = availableBalance;
                eventInfo.classList.remove('hidden');
            } else {
                document.getElementById('payoutEventId').value = '';
                document.getElementById('modalAvailableBalance').textContent = formatCurrency(<?= $financeData['available_balance'] ?>);
                document.getElementById('payoutAmountHint').textContent = `Suma minima: 100 RON, maxima: ${formatCurrency(<?= $financeData['available_balance'] ?>)}`;
                currentMaxAmount = <?= $financeData['available_balance'] ?>;
                eventInfo.classList.add('hidden');
            }

            document.getElementById('payoutAmount').value = '';
            document.getElementById('payoutAmount').max = currentMaxAmount;
            document.getElementById('payoutNotes').value = '';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePayoutModal() {
            const modal = document.getElementById('payoutModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function submitPayout(event) {
            event.preventDefault();

            const amount = parseFloat(document.getElementById('payoutAmount').value);
            const account = document.getElementById('payoutAccount').value;

            if (!account) {
                alert('Selecteaza un cont bancar.');
                return;
            }

            if (amount < 100) {
                alert('Suma minima pentru retragere este 100 RON.');
                return;
            }

            if (amount > currentMaxAmount) {
                alert(`Suma maxima disponibila este ${formatCurrency(currentMaxAmount)}.`);
                return;
            }

            alert(`Cererea de plata pentru ${formatCurrency(amount)} a fost trimisa cu succes! Vei primi banii in 2-3 zile lucratoare. (Demo)`);
            closePayoutModal();
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('ro-RO').format(amount) + ' RON';
        }
    </script>
</body>
</html>
