<?php
/**
 * Checkout Processing Page
 * Full-screen payment processing animation
 */
require_once 'includes/config.php';

$pageTitle = 'Se procesează plata... - AmBilet.ro';
$pageDescription = 'Plata ta este în curs de procesare. Te rugăm să aștepți.';

require_once 'includes/head.php';
?>

<body class="font-['Plus_Jakarta_Sans'] bg-gradient-to-br from-slate-800 to-slate-900 text-white min-h-screen flex flex-col items-center justify-center p-8">
    <div class="text-center max-w-md">
        <!-- Logo -->
        <a href="/" class="flex items-center justify-center gap-3 mb-12 no-underline">
            <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
                <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#grad)"/>
                <path d="M4 16C4 13.79 5.79 12 8 12H14V36H8C5.79 36 4 34.21 4 32V16Z" fill="white" opacity="0.3"/>
                <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                <rect x="20" y="18" width="12" height="2" rx="1" fill="white" opacity="0.8"/>
                <rect x="20" y="23" width="18" height="2" rx="1" fill="white" opacity="0.6"/>
                <defs><linearGradient id="grad" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
            </svg>
            <span class="text-[1.75rem] font-bold">
                <span class="text-white">Am</span><span class="text-red-400">Bilet</span>
            </span>
        </a>

        <!-- Animation Container -->
        <div class="w-40 h-40 mx-auto mb-8 relative">
            <!-- Spinner Rings -->
            <div class="absolute inset-0 rounded-full border-4 border-white/10 border-t-primary animate-spin"></div>
            <div class="absolute inset-[10%] rounded-full border-4 border-white/10 border-t-red-400 animate-spin-reverse" style="animation-duration: 1.5s;"></div>
            <div class="absolute inset-[20%] rounded-full border-4 border-white/10 border-t-amber-400 animate-spin" style="animation-duration: 2s;"></div>

            <!-- Center Icon -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 bg-gradient-to-br from-primary to-primary-light rounded-full flex items-center justify-center shadow-[0_0_40px_rgba(165,28,48,0.5)]">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
        </div>

        <!-- Text -->
        <h1 class="text-2xl font-bold mb-3">Se procesează plata</h1>
        <p class="text-base text-slate-400 mb-8">Te rugăm să aștepți câteva secunde...</p>

        <!-- Progress Steps -->
        <div class="flex flex-col gap-3 text-left bg-white/5 rounded-2xl p-6 backdrop-blur-sm">
            <div class="step flex items-center gap-3 text-[0.9375rem]" id="step1" data-status="completed">
                <span class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-green-500 text-white">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                <span class="text-slate-400">Verificare date card</span>
            </div>
            <div class="step flex items-center gap-3 text-[0.9375rem]" id="step2" data-status="active">
                <span class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-primary text-white animate-pulse">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                    </svg>
                </span>
                <span class="text-white font-semibold">Autorizare plată</span>
            </div>
            <div class="step flex items-center gap-3 text-[0.9375rem]" id="step3" data-status="pending">
                <span class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-white/10 text-slate-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                    </svg>
                </span>
                <span class="text-slate-500">Confirmare și emitere bilete</span>
            </div>
        </div>

        <!-- Security Badge -->
        <div class="flex items-center justify-center gap-2 mt-8 text-[0.8125rem] text-slate-500">
            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Conexiune securizată SSL • Date criptate
        </div>

        <!-- Warning -->
        <div class="mt-8 p-4 bg-amber-400/10 border border-amber-400/30 rounded-xl text-[0.8125rem] text-amber-400 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Nu închide această fereastră și nu naviga în altă parte
        </div>
    </div>

    <style>
        @keyframes spin-reverse {
            to { transform: rotate(-360deg); }
        }
        .animate-spin-reverse {
            animation: spin-reverse 1s linear infinite;
        }
    </style>

    <script>
        // Simulate progress (for demo)
        function updateStep(stepId, status) {
            const step = document.getElementById(stepId);
            const icon = step.querySelector('.step-icon');
            const text = step.querySelector('span:last-child');

            step.dataset.status = status;

            if (status === 'completed') {
                icon.className = 'step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-green-500 text-white';
                icon.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                text.className = 'text-slate-400';
            } else if (status === 'active') {
                icon.className = 'step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-primary text-white animate-pulse';
                text.className = 'text-white font-semibold';
            }
        }

        setTimeout(() => {
            updateStep('step2', 'completed');
            updateStep('step3', 'active');
            document.getElementById('step3').querySelector('.step-icon').innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>';
        }, 3000);

        setTimeout(() => {
            updateStep('step3', 'completed');
            // Redirect to thank you page
            // window.location.href = '/checkout/thank-you';
        }, 5000);
    </script>
</body>
</html>
