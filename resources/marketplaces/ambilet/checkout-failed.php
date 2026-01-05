<?php
/**
 * Checkout Failed Page
 * Payment error with retry options
 */
require_once 'includes/config.php';

$pageTitle = 'Plată nereușită - AmBilet.ro';
$pageDescription = 'Plata nu a fost procesată. Încearcă din nou sau folosește o altă metodă de plată.';

// Demo data - would come from session/database in real implementation
$errorCode = $_GET['error'] ?? 'ERR_CARD_DECLINED_51';
$orderTotal = '1.596,50';

require_once 'includes/head.php';
?>

<body class="font-['Plus_Jakarta_Sans'] bg-slate-50 text-slate-800 leading-relaxed min-h-screen">
    <!-- Simple Header -->
    <header class="bg-white border-b border-slate-200 py-4 px-4 md:px-8">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 no-underline">
                <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                    <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#gradF)"/>
                    <path d="M4 16C4 13.79 5.79 12 8 12H14V36H8C5.79 36 4 34.21 4 32V16Z" fill="#1E293B"/>
                    <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                    <rect x="20" y="18" width="12" height="2" rx="1" fill="white" opacity="0.8"/>
                    <rect x="20" y="23" width="18" height="2" rx="1" fill="white" opacity="0.6"/>
                    <defs><linearGradient id="gradF" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
                </svg>
                <span class="text-2xl font-bold">
                    <span class="text-slate-800">Am</span><span class="text-primary">Bilet</span>
                </span>
            </a>
            <div class="flex items-center gap-2 text-[0.8125rem] text-slate-500">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="hidden sm:inline">Conexiune securizată</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-[900px] mx-auto my-8 px-4 grid lg:grid-cols-[1.2fr_0.8fr] gap-8 items-start">
        <!-- Error Card -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <!-- Error Header -->
            <div class="bg-gradient-to-br from-red-600 to-red-700 p-8 text-center text-white">
                <div class="w-[72px] h-[72px] bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 animate-shake">
                    <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold mb-2">Plata nu a fost procesată</h1>
                <p class="text-[0.9375rem] opacity-90">Ne pare rău, dar tranzacția nu a putut fi finalizată</p>
                <span class="inline-block mt-3 px-3 py-1.5 bg-black/20 rounded font-mono text-[0.8125rem]">
                    Cod eroare: <?= htmlspecialchars($errorCode) ?>
                </span>
            </div>

            <!-- Error Body -->
            <div class="p-6 md:p-8">
                <!-- Error Reason -->
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h3 class="text-[0.9375rem] font-semibold text-red-800 mb-1">Fonduri insuficiente</h3>
                        <p class="text-sm text-red-700">Cardul nu are suficiente fonduri pentru această tranzacție sau limita de cheltuieli a fost atinsă.</p>
                    </div>
                </div>

                <!-- Solutions -->
                <h3 class="text-base font-semibold text-slate-800 mb-4">Ce poți face:</h3>
                <div class="flex flex-col gap-3 mb-6">
                    <label class="solution-item flex items-start gap-3 p-4 bg-slate-50 rounded-xl cursor-pointer transition-all border-2 border-transparent hover:border-primary peer-checked:border-primary peer-checked:bg-red-50">
                        <input type="radio" name="solution" value="retry" class="peer hidden" checked>
                        <div class="solution-radio w-5 h-5 border-2 border-slate-300 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center peer-checked:border-primary peer-checked:bg-primary">
                            <div class="w-2 h-2 bg-white rounded-full opacity-0 peer-checked:opacity-100"></div>
                        </div>
                        <div>
                            <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Încearcă din nou cu același card</h4>
                            <p class="text-[0.8125rem] text-slate-500">Verifică dacă ai fonduri suficiente și încearcă din nou</p>
                        </div>
                    </label>
                    <label class="solution-item flex items-start gap-3 p-4 bg-slate-50 rounded-xl cursor-pointer transition-all border-2 border-transparent hover:border-primary">
                        <input type="radio" name="solution" value="other-card" class="peer hidden">
                        <div class="solution-radio w-5 h-5 border-2 border-slate-300 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center">
                            <div class="w-2 h-2 bg-white rounded-full opacity-0"></div>
                        </div>
                        <div>
                            <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Folosește alt card</h4>
                            <p class="text-[0.8125rem] text-slate-500">Încearcă cu un card diferit sau adaugă un card nou</p>
                        </div>
                    </label>
                    <label class="solution-item flex items-start gap-3 p-4 bg-slate-50 rounded-xl cursor-pointer transition-all border-2 border-transparent hover:border-primary">
                        <input type="radio" name="solution" value="installments" class="peer hidden">
                        <div class="solution-radio w-5 h-5 border-2 border-slate-300 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center">
                            <div class="w-2 h-2 bg-white rounded-full opacity-0"></div>
                        </div>
                        <div>
                            <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Plătește în rate</h4>
                            <p class="text-[0.8125rem] text-slate-500">Împarte suma în 3-6 rate fără dobândă cu BT Pay</p>
                        </div>
                    </label>
                </div>

                <!-- Alternative Payment Methods -->
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Sau alege altă metodă de plată</p>
                <div class="flex flex-wrap gap-2 mb-6">
                    <button class="payment-method px-4 py-3 bg-white border border-slate-200 rounded-lg cursor-pointer transition-all hover:border-primary flex items-center gap-2 text-sm font-medium">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#1A1F71"/><text x="12" y="15" font-size="6" fill="white" text-anchor="middle" font-weight="bold">VISA</text></svg>
                        Card nou
                    </button>
                    <button class="payment-method px-4 py-3 bg-white border border-slate-200 rounded-lg cursor-pointer transition-all hover:border-primary flex items-center gap-2 text-sm font-medium">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="12" r="7" fill="#EB001B"/><circle cx="15" cy="12" r="7" fill="#F79E1B" fill-opacity="0.8"/></svg>
                        Mastercard
                    </button>
                    <button class="payment-method px-4 py-3 bg-white border border-slate-200 rounded-lg cursor-pointer transition-all hover:border-primary flex items-center gap-2 text-sm font-medium">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#00457C"/><text x="12" y="15" font-size="5" fill="white" text-anchor="middle" font-weight="bold">PayPal</text></svg>
                        PayPal
                    </button>
                    <button class="payment-method px-4 py-3 bg-white border border-slate-200 rounded-lg cursor-pointer transition-all hover:border-primary flex items-center gap-2 text-sm font-medium">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#000"/><text x="12" y="15" font-size="6" fill="white" text-anchor="middle">G</text></svg>
                        Google Pay
                    </button>
                </div>

                <!-- Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="/cos" class="flex-1 py-3.5 px-6 rounded-lg font-semibold text-[0.9375rem] cursor-pointer border-none transition-all no-underline text-center flex items-center justify-center gap-2 bg-slate-100 text-slate-600 hover:bg-slate-200">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Înapoi la coș
                    </a>
                    <button class="flex-1 py-3.5 px-6 rounded-lg font-semibold text-[0.9375rem] cursor-pointer border-none transition-all text-center flex items-center justify-center gap-2 bg-gradient-to-br from-primary to-primary-light text-white hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Încearcă din nou
                    </button>
                </div>

                <!-- Help Section -->
                <div class="mt-6 pt-6 border-t border-slate-200">
                    <p class="text-[0.8125rem] text-slate-500 mb-2">Ai nevoie de ajutor? Echipa noastră de suport îți stă la dispoziție.</p>
                    <a href="/contact" class="text-primary font-semibold text-sm no-underline hover:underline">Contactează suport →</a>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sticky top-8">
            <h2 class="text-base font-semibold text-slate-800 mb-4 pb-4 border-b border-slate-200">Rezumat comandă</h2>

            <div class="flex gap-4 mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex-shrink-0"></div>
                <div>
                    <h3 class="text-[0.9375rem] font-semibold text-slate-800 mb-1">Coldplay Concert</h3>
                    <p class="text-[0.8125rem] text-slate-500">15 Iunie 2025, 20:00</p>
                    <p class="text-[0.8125rem] text-slate-500">Arena Națională, București</p>
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-4 mb-4">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-slate-500">Golden Circle VIP × 2</span>
                    <span class="font-medium text-slate-800">1.200 RON</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-slate-500">Tribuna 1 - Cat. A × 1</span>
                    <span class="font-medium text-slate-800">350 RON</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Taxă servicii</span>
                    <span class="font-medium text-slate-800">46,50 RON</span>
                </div>
            </div>

            <div class="flex justify-between pt-4 border-t border-slate-200 font-bold">
                <span>Total</span>
                <span class="text-xl text-primary"><?= $orderTotal ?> RON</span>
            </div>

            <!-- Timer Warning -->
            <div class="mt-4 p-3 bg-amber-50 rounded-lg flex items-center gap-2 text-[0.8125rem] text-amber-700">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Biletele sunt rezervate încă <span class="timer font-bold font-mono">09:45</span> minute
            </div>
        </div>
    </div>

    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }

        .solution-item:has(input:checked) {
            border-color: #A51C30;
            background-color: #FEF2F2;
        }
        .solution-item:has(input:checked) .solution-radio {
            border-color: #A51C30;
            background-color: #A51C30;
        }
        .solution-item:has(input:checked) .solution-radio > div {
            opacity: 1;
        }
    </style>

    <script>
        // Countdown timer
        let time = 9 * 60 + 45;
        const timerEl = document.querySelector('.timer');

        setInterval(() => {
            if (time > 0) {
                time--;
                const mins = Math.floor(time / 60);
                const secs = time % 60;
                timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
        }, 1000);
    </script>
</body>
</html>
