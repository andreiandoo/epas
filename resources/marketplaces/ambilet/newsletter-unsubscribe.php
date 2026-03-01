<?php
/**
 * Newsletter Unsubscribe Page
 * Manage email subscription preferences
 */
require_once 'includes/config.php';

$pageTitle = 'Dezabonare Newsletter - AmBilet.ro';
$pageDescription = 'Gestionează preferințele tale de comunicare pentru newsletter-ul AmBilet.';

// Get email from URL token (would be validated in real implementation)
$email = 'andrei.p***@gmail.com'; // Masked for privacy

$cssBundle = 'static';
require_once 'includes/head.php';
?>

<body class="font-['Plus_Jakarta_Sans'] bg-slate-50 min-h-screen flex flex-col text-slate-800">
    <!-- Simple Header -->
    <header class="bg-white border-b border-slate-200 py-4 px-8">
        <div class="max-w-[600px] mx-auto flex justify-center">
            <a href="/" class="flex items-center gap-3 no-underline">
                <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                    <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#gradU)"/>
                    <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                    <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                    <defs><linearGradient id="gradU" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
                </svg>
                <span class="text-2xl font-bold">
                    <span class="text-slate-800">Am</span><span class="text-primary">Bilet</span>
                </span>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <div class="flex-1 flex items-center justify-center p-8">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-[500px] w-full overflow-hidden shadow-lg shadow-black/5">
            <!-- Unsubscribe Form -->
            <div id="unsubscribe-form">
                <!-- Card Header -->
                <div class="p-8 text-center bg-gradient-to-br from-red-50 to-red-100 border-b border-red-200">
                    <div class="w-16 h-16 mx-auto mb-4 bg-white rounded-full flex items-center justify-center shadow-md">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h1 class="text-[1.375rem] font-bold text-slate-800 mb-1">Gestionează abonamentul</h1>
                    <p class="text-[0.9375rem] text-slate-500">Alege preferințele tale de comunicare</p>
                </div>

                <!-- Card Body -->
                <div class="p-8">
                    <!-- Email Display -->
                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl mb-6">
                        <div class="w-10 h-10 bg-white rounded-lg border border-slate-200 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <span class="text-[0.9375rem] font-medium text-slate-800"><?= htmlspecialchars($email) ?></span>
                    </div>

                    <!-- Options Title -->
                    <div class="text-sm font-semibold text-slate-800 mb-3">Ce preferi să faci?</div>

                    <!-- Option Cards -->
                    <label class="option-card block border border-slate-200 rounded-xl p-4 mb-3 cursor-pointer transition-all hover:border-primary hover:bg-red-50">
                        <input type="radio" name="preference" value="less" class="hidden">
                        <div class="flex items-center gap-3">
                            <div class="option-radio w-5 h-5 border-2 border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 transition-all">
                                <div class="w-2.5 h-2.5 bg-primary rounded-full opacity-0 transition-opacity"></div>
                            </div>
                            <div>
                                <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Primește mai puține emailuri</h4>
                                <p class="text-[0.8125rem] text-slate-500">Doar anunțuri importante și evenimente exclusive</p>
                            </div>
                        </div>
                    </label>

                    <label class="option-card block border border-slate-200 rounded-xl p-4 mb-3 cursor-pointer transition-all hover:border-primary hover:bg-red-50">
                        <input type="radio" name="preference" value="pause" class="hidden">
                        <div class="flex items-center gap-3">
                            <div class="option-radio w-5 h-5 border-2 border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 transition-all">
                                <div class="w-2.5 h-2.5 bg-primary rounded-full opacity-0 transition-opacity"></div>
                            </div>
                            <div>
                                <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Pauză temporară (30 zile)</h4>
                                <p class="text-[0.8125rem] text-slate-500">Nu vei primi emailuri în următoarele 30 de zile</p>
                            </div>
                        </div>
                    </label>

                    <label class="option-card block border border-slate-200 rounded-xl p-4 mb-3 cursor-pointer transition-all hover:border-primary hover:bg-red-50 selected">
                        <input type="radio" name="preference" value="unsubscribe" class="hidden" checked>
                        <div class="flex items-center gap-3">
                            <div class="option-radio w-5 h-5 border-2 border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 transition-all">
                                <div class="w-2.5 h-2.5 bg-primary rounded-full opacity-0 transition-opacity"></div>
                            </div>
                            <div>
                                <h4 class="text-[0.9375rem] font-semibold text-slate-800 mb-0.5">Dezabonare completă</h4>
                                <p class="text-[0.8125rem] text-slate-500">Nu mai primi niciun email promotional</p>
                            </div>
                        </div>
                    </label>

                    <!-- Submit Button -->
                    <button onclick="showSuccess()" class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-semibold text-[0.9375rem] cursor-pointer border-none transition-all bg-gradient-to-br from-primary to-primary-light text-white mt-6 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Confirmă alegerea
                    </button>

                    <!-- Cancel Link -->
                    <a href="/" class="block text-center mt-4 text-slate-500 text-sm no-underline hover:text-primary">
                        Anulează și păstrează setările actuale
                    </a>
                </div>
            </div>

            <!-- Success State -->
            <div id="success-state" class="hidden text-center p-8">
                <div class="w-20 h-20 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Te-ai dezabonat cu succes</h2>
                <p class="text-[0.9375rem] text-slate-500 mb-6 leading-relaxed">
                    Nu vei mai primi emailuri promoționale de la AmBilet. Vei primi în continuare emailuri tranzacționale importante (confirmări bilete, actualizări evenimente, etc.)
                </p>

                <a href="/" class="inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-semibold text-[0.9375rem] cursor-pointer border-none transition-all bg-gradient-to-br from-primary to-primary-light text-white no-underline hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Înapoi la AmBilet
                </a>

                <!-- Resubscribe Box -->
                <div class="bg-slate-50 rounded-xl p-5 mt-6">
                    <h4 class="text-sm font-semibold text-slate-800 mb-2">Te-ai răzgândit?</h4>
                    <p class="text-[0.8125rem] text-slate-500 mb-3">Poți oricând să te reabonezi pentru a primi noutăți despre evenimente și oferte exclusive.</p>
                    <button class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-[0.8125rem] font-semibold text-slate-800 cursor-pointer transition-all hover:border-primary hover:text-primary">
                        Reabonează-mă
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-6 px-8 text-center text-slate-500 text-[0.8125rem]">
        <p>
            Ai întrebări? <a href="/contact" class="text-primary no-underline">Contactează-ne</a> |
            <a href="/confidentialitate" class="text-primary no-underline">Politica de confidențialitate</a>
        </p>
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

    <script>
        // Show success state
        function showSuccess() {
            document.getElementById('unsubscribe-form').style.display = 'none';
            document.getElementById('success-state').classList.remove('hidden');
        }
    </script>
</body>
</html>
