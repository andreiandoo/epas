<?php
/**
 * Event Rescheduled Page
 * Notification about rescheduled event with options
 */
require_once 'includes/config.php';

$pageTitle = 'Eveniment Reprogramat - AmBilet.ro';
$pageDescription = 'Evenimentul a fost reprogramat. Alege dacă vrei să păstrezi biletele sau să soliciți rambursare.';

// Demo data - would come from database in real implementation
$event = [
    'name' => 'Coldplay - Music of the Spheres World Tour',
    'venue' => 'Arena Națională, București',
    'order_id' => 'AMB-2024-00847',
    'old_date' => 'Sâm, 15 Iunie 2025',
    'old_time' => 'Ora 20:00',
    'new_date' => 'Dum, 22 Iunie 2025',
    'new_time' => 'Ora 20:00',
    'refund_deadline' => '15 Iunie 2025'
];
$tickets = [
    'type' => '2× Golden Circle VIP',
    'value' => '1.200,00',
    'status' => 'Valabil'
];

$cssBundle = 'event';
require_once 'includes/head.php';
?>

<body class="font-['Plus_Jakarta_Sans'] bg-slate-50 min-h-screen flex flex-col text-slate-800">
    <!-- Simple Header -->
    <header class="bg-white border-b border-slate-200 py-4 px-8">
        <div class="max-w-[800px] mx-auto flex justify-center">
            <a href="/" class="flex items-center gap-3 no-underline">
                <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                    <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#gradR)"/>
                    <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                    <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                    <defs><linearGradient id="gradR" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
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
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 py-5 px-8 flex items-center gap-4 text-white">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold mb-0.5">Eveniment reprogramat</h2>
                    <p class="text-sm opacity-90">Organizatorul a schimbat data evenimentului</p>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-6 md:p-8">
                <!-- Event Card -->
                <div class="flex flex-col md:flex-row gap-5 p-5 bg-slate-50 rounded-2xl mb-6">
                    <div class="w-full md:w-[100px] h-[120px] md:h-[100px] rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex-shrink-0 flex items-center justify-center">
                        <svg class="w-10 h-10 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 mb-2"><?= htmlspecialchars($event['name']) ?></h3>
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2 text-[0.8125rem] text-slate-500">
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

                <!-- Date Change -->
                <div class="mb-6">
                    <div class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Schimbare dată
                    </div>
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="flex-1 w-full p-4 rounded-xl text-center bg-red-100 border border-red-200">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-red-800 mb-2">Data inițială</div>
                            <div class="text-base font-bold text-slate-400 line-through mb-0.5"><?= htmlspecialchars($event['old_date']) ?></div>
                            <div class="text-[0.8125rem] text-slate-500"><?= htmlspecialchars($event['old_time']) ?></div>
                        </div>
                        <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center flex-shrink-0 md:rotate-0 rotate-90">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </div>
                        <div class="flex-1 w-full p-4 rounded-xl text-center bg-green-100 border border-green-200">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-green-800 mb-2">Data nouă</div>
                            <div class="text-base font-bold text-slate-800 mb-0.5"><?= htmlspecialchars($event['new_date']) ?></div>
                            <div class="text-[0.8125rem] text-slate-500"><?= htmlspecialchars($event['new_time']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-blue-800 leading-relaxed">
                        <strong class="font-semibold">Biletele tale rămân valabile</strong> pentru noua dată. Nu trebuie să faci nimic dacă poți participa. Dacă nu poți, ai opțiunea de a solicita rambursarea.
                    </p>
                </div>

                <!-- Ticket Status -->
                <div class="bg-slate-50 rounded-xl p-5 mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-base font-semibold text-slate-800">Biletele tale</span>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 md:gap-8">
                        <div>
                            <label class="text-xs text-slate-500 uppercase tracking-wide block mb-1">Tip bilet</label>
                            <span class="text-[0.9375rem] font-semibold text-slate-800"><?= htmlspecialchars($tickets['type']) ?></span>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500 uppercase tracking-wide block mb-1">Valoare totală</label>
                            <span class="text-[0.9375rem] font-semibold text-slate-800"><?= $tickets['value'] ?> RON</span>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500 uppercase tracking-wide block mb-1">Status</label>
                            <span class="text-[0.9375rem] font-semibold text-green-500">✓ <?= $tickets['status'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Options -->
                <div class="mb-6">
                    <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-3">Ce dorești să faci?</h4>
                    <div class="flex flex-col gap-3">
                        <label class="option-card flex items-center gap-4 p-4 border border-slate-200 rounded-xl cursor-pointer transition-all hover:border-primary hover:bg-red-50">
                            <input type="radio" name="choice" value="keep" class="hidden" checked>
                            <div class="option-radio w-5 h-5 border-2 border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 transition-all">
                                <div class="w-2.5 h-2.5 bg-primary rounded-full opacity-0 transition-opacity"></div>
                            </div>
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-green-100 text-green-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Păstrează biletele</h5>
                                <p class="text-[0.8125rem] text-slate-500">Voi participa la noua dată - biletele rămân valabile</p>
                            </div>
                        </label>
                        <label class="option-card flex items-center gap-4 p-4 border border-slate-200 rounded-xl cursor-pointer transition-all hover:border-primary hover:bg-red-50">
                            <input type="radio" name="choice" value="refund" class="hidden">
                            <div class="option-radio w-5 h-5 border-2 border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 transition-all">
                                <div class="w-2.5 h-2.5 bg-primary rounded-full opacity-0 transition-opacity"></div>
                            </div>
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-red-100 text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Solicită rambursare</h5>
                                <p class="text-[0.8125rem] text-slate-500">Nu pot participa - doresc rambursarea integrală</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-semibold text-[0.9375rem] cursor-pointer border-none transition-all bg-gradient-to-br from-primary to-primary-light text-white hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirmă alegerea
                </button>

                <!-- Deadline Notice -->
                <p class="text-center mt-4 text-[0.8125rem] text-slate-500">
                    Poți solicita rambursare până la <strong class="text-primary"><?= htmlspecialchars($event['refund_deadline']) ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-6 px-8 text-center text-slate-500 text-[0.8125rem]">
        <p>&copy; <?= date('Y') ?> AmBilet.ro - Toate drepturile rezervate</p>
    </footer>

    <style>
        .option-card:has(input:checked) {
            border-color: #A51C30;
            background-color: #FEF2F2;
        }
        .option-card:has(input:checked) .option-radio {
            border-color: #A51C30;
        }
        .option-card:has(input:checked) .option-radio > div {
            opacity: 1;
        }
    </style>
</body>
</html>
