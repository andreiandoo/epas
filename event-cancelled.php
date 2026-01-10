<?php
/**
 * Event Cancelled Page
 * Notification about cancelled event with refund info
 */
require_once 'includes/config.php';

$pageTitle = 'Eveniment Anulat - AmBilet.ro';
$pageDescription = 'Evenimentul a fost anulat. Informații despre rambursare automată.';

// Demo data - would come from database in real implementation
$event = [
    'name' => 'Concert Acoustic Night - Sunset Sessions',
    'date' => 'Vineri, 20 Decembrie 2024 • 20:00',
    'venue' => 'Hard Rock Cafe, București',
    'order_id' => 'AMB-2024-05892'
];
$refund = [
    'amount' => '298,00',
    'processing_time' => '5-10 zile lucrătoare',
    'tickets' => '2× Bilet Standard',
    'ticket_price' => '280,00',
    'service_fee' => '18,00'
];

require_once 'includes/head.php';
?>

<body class="font-['Plus_Jakarta_Sans'] bg-slate-50 min-h-screen flex flex-col text-slate-800">
    <!-- Simple Header -->
    <header class="bg-white border-b border-slate-200 py-4 px-8">
        <div class="max-w-[800px] mx-auto flex justify-center">
            <a href="/" class="flex items-center gap-3 no-underline">
                <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                    <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#gradC)"/>
                    <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                    <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                    <defs><linearGradient id="gradC" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
                </svg>
                <span class="text-2xl font-bold">
                    <span class="text-slate-800">Am</span><span class="text-primary">Bilet</span>
                </span>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <div class="flex-1 flex items-center justify-center p-4 md:p-8">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-[600px] w-full overflow-hidden shadow-lg shadow-black/5">
            <!-- Alert Banner -->
            <div class="bg-gradient-to-br from-red-500 to-red-600 py-5 px-8 flex items-center gap-4 text-white">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold mb-0.5">Eveniment anulat</h2>
                    <p class="text-sm opacity-90">Ne pare rău, acest eveniment a fost anulat de organizator</p>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-6 md:p-8">
                <!-- Event Card -->
                <div class="flex flex-col md:flex-row gap-5 p-5 bg-slate-50 rounded-2xl mb-6">
                    <div class="w-full md:w-[100px] h-[120px] md:h-[100px] rounded-xl bg-gradient-to-br from-slate-400 to-slate-500 flex-shrink-0 flex items-center justify-center relative">
                        <svg class="w-10 h-10 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                        <div class="absolute inset-0 bg-black/60 rounded-xl flex items-center justify-center">
                            <span class="bg-red-500 text-white px-3 py-1 rounded text-[11px] font-bold uppercase tracking-wide">Anulat</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-400 line-through mb-2"><?= htmlspecialchars($event['name']) ?></h3>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-[0.8125rem] text-slate-500 line-through">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= htmlspecialchars($event['date']) ?>
                            </div>
                            <div class="flex items-center gap-2 text-[0.8125rem] text-slate-500 line-through">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                <?= htmlspecialchars($event['venue']) ?>
                            </div>
                            <div class="flex items-center gap-2 text-[0.8125rem] text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                                Comandă #<?= htmlspecialchars($event['order_id']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Section -->
                <div class="mb-6">
                    <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        De ce a fost anulat?
                    </h4>
                    <p class="text-[0.9375rem] text-slate-500 leading-relaxed">
                        Organizatorul a anulat evenimentul din motive neprevăzute. Ne cerem scuze pentru inconveniența cauzată. Toate biletele achiziționate vor fi rambursate automat.
                    </p>
                </div>

                <!-- Refund Box -->
                <div class="bg-green-100 border border-green-500 rounded-xl p-5 mb-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="text-base font-semibold text-green-800">Rambursare automată inițiată</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-green-800 uppercase tracking-wide block mb-1">Sumă rambursată</label>
                            <span class="text-base font-semibold text-green-800"><?= $refund['amount'] ?> RON</span>
                        </div>
                        <div>
                            <label class="text-xs text-green-800 uppercase tracking-wide block mb-1">Termen procesare</label>
                            <span class="text-base font-semibold text-green-800"><?= $refund['processing_time'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="bg-slate-50 rounded-xl p-4 mb-6">
                    <div class="flex justify-between py-2 text-sm">
                        <span class="text-slate-500"><?= $refund['tickets'] ?></span>
                        <span class="text-slate-800 font-medium"><?= $refund['ticket_price'] ?> RON</span>
                    </div>
                    <div class="flex justify-between py-2 text-sm">
                        <span class="text-slate-500">Taxe servicii</span>
                        <span class="text-slate-800 font-medium"><?= $refund['service_fee'] ?> RON</span>
                    </div>
                    <div class="flex justify-between pt-3 mt-2 border-t border-slate-200 font-bold">
                        <span class="text-slate-500">Total rambursat</span>
                        <span class="text-green-500"><?= $refund['amount'] ?> RON</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="/evenimente" class="flex-1 inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-semibold text-[0.9375rem] cursor-pointer border-none transition-all no-underline bg-gradient-to-br from-primary to-primary-light text-white hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Descoperă alte evenimente
                    </a>
                    <a href="/cont/comenzi" class="flex-1 inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-semibold text-[0.9375rem] cursor-pointer transition-all no-underline bg-white text-slate-800 border border-slate-200 hover:bg-slate-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Vezi comenzile
                    </a>
                </div>

                <!-- Help Link -->
                <p class="text-center mt-5 text-sm text-slate-500">
                    Ai întrebări despre rambursare? <a href="/contact" class="text-primary font-medium no-underline">Contactează suportul</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-6 px-8 text-center text-slate-500 text-[0.8125rem]">
        <p>&copy; <?= date('Y') ?> AmBilet.ro - Toate drepturile rezervate</p>
    </footer>
</body>
</html>
