<?php
/**
 * Organizer Payouts Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];
$transactions = $demoData['transactions'] ?? [];

// Current page for sidebar
$currentPage = 'payouts';

// Page config for head
$pageTitle = 'Plati & Incasari';
$pageDescription = 'Gestioneaza veniturile si retragerile';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between px-8 py-4">
                <div><h1 class="text-2xl font-bold text-gray-900">Plati & Incasari</h1><p class="text-sm text-gray-500">Gestioneaza veniturile si retragerile</p></div>
                <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-xl hover:bg-green-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Solicita retragere</button>
            </div>
        </header>

        <div class="p-8">
            <!-- Balance Cards -->
            <div class="grid sm:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white animate-fadeInUp">
                    <p class="text-green-100 text-sm mb-1">Balanta disponibila</p>
                    <p class="text-4xl font-bold"><?= number_format($stats['pendingPayout'] ?? 45230, 0, ',', '.') ?> <span class="text-xl">RON</span></p>
                    <p class="text-green-200 text-sm mt-2">Poate fi retrasa oricand</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <p class="text-gray-500 text-sm mb-1">In procesare</p>
                    <p class="text-3xl font-bold text-gray-900">12.450 <span class="text-lg">RON</span></p>
                    <p class="text-gray-400 text-sm mt-2">Disponibil in 2-3 zile</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.15s">
                    <p class="text-gray-500 text-sm mb-1">Total castigat</p>
                    <p class="text-3xl font-bold text-gray-900"><?= number_format($stats['totalRevenue'] ?? 127450, 0, ',', '.') ?> <span class="text-lg">RON</span></p>
                    <p class="text-gray-400 text-sm mt-2">Din <?= $stats['activeEvents'] ?? 5 ?> evenimente</p>
                </div>
            </div>

            <!-- Bank Account -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8 animate-fadeInUp" style="animation-delay: 0.2s">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Cont bancar pentru plati</h2>
                    <a href="/organizator/setari" class="text-sm text-indigo-600 font-medium hover:underline">Modifica</a>
                </div>
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">ING Bank</p>
                        <p class="text-sm text-gray-500">RO49 INGB **** **** **** 4521 • <?= htmlspecialchars($currentOrganizer['companyName']) ?></p>
                    </div>
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Verificat</span>
                </div>
            </div>

            <!-- Transactions -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden animate-fadeInUp" style="animation-delay: 0.25s">
                <div class="flex items-center justify-between p-6 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900">Istoric tranzactii</h2>
                    <select class="border border-gray-200 rounded-xl px-4 py-2 text-sm"><option>Toate</option><option>Incasari</option><option>Retrageri</option><option>Comisioane</option></select>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50"><tr><th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Descriere</th><th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Eveniment</th><th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Data</th><th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Suma</th><th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></div><span class="font-medium text-gray-900">Vanzare bilete</span></div></td>
                            <td class="px-6 py-4 text-sm text-gray-600">Coldplay Concert</td>
                            <td class="px-6 py-4 text-sm text-gray-500">30 Ian 2026, 14:32</td>
                            <td class="px-6 py-4 text-right font-semibold text-green-600">+698 RON</td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Finalizat</span></td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></div><span class="font-medium text-gray-900">Comision platforma (1%)</span></div></td>
                            <td class="px-6 py-4 text-sm text-gray-600">Coldplay Concert</td>
                            <td class="px-6 py-4 text-sm text-gray-500">30 Ian 2026, 14:32</td>
                            <td class="px-6 py-4 text-right font-semibold text-red-600">-6.98 RON</td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded-full">Dedus automat</span></td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9"/></svg></div><span class="font-medium text-gray-900">Retragere in cont</span></div></td>
                            <td class="px-6 py-4 text-sm text-gray-500">—</td>
                            <td class="px-6 py-4 text-sm text-gray-500">28 Ian 2026, 10:00</td>
                            <td class="px-6 py-4 text-right font-semibold text-gray-900">-25.000 RON</td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Transferat</span></td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></div><span class="font-medium text-gray-900">Vanzare bilete</span></div></td>
                            <td class="px-6 py-4 text-sm text-gray-600">Stand-up Comedy</td>
                            <td class="px-6 py-4 text-sm text-gray-500">27 Ian 2026, 16:45</td>
                            <td class="px-6 py-4 text-right font-semibold text-green-600">+396 RON</td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Finalizat</span></td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                    <p class="text-sm text-gray-500">Afisare 1-4 din 156 tranzactii</p>
                    <div class="flex items-center gap-2"><button class="w-10 h-10 bg-indigo-600 text-white font-medium rounded-lg">1</button><button class="w-10 h-10 text-gray-600 hover:bg-gray-100 rounded-lg">2</button><button class="w-10 h-10 text-gray-600 hover:bg-gray-100 rounded-lg">3</button></div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
