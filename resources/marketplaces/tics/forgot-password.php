<?php
/**
 * TICS.ro - Forgot Password Page
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Resetare parolă';
$pageDescription = 'Resetează-ți parola contului TICS.';
$hideCategoriesBar = true;
$bodyClass = 'bg-gray-50';

$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
    .form-input { transition: all 0.2s ease; }
    .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
</style>
HTML;

include __DIR__ . '/includes/head.php';

// Get step from query (for demo)
$step = $_GET['step'] ?? 1;
?>

<!-- Minimal Header -->
<header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="/" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">T</span>
                </div>
                <span class="font-bold text-lg">TICS</span>
            </a>
            <a href="/conectare" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">← Înapoi la conectare</a>
        </div>
    </div>
</header>

<main class="min-h-[calc(100vh-180px)] flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <?php if ($step == 1): ?>
        <!-- Step 1: Email -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-lg animate-fadeInUp">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Ai uitat parola?</h1>
                <p class="text-gray-500">Introdu emailul și îți trimitem un link de resetare.</p>
            </div>

            <form action="?step=2" method="GET" class="space-y-4">
                <input type="hidden" name="step" value="2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="email@exemplu.com">
                </div>

                <button type="submit" class="w-full py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                    Trimite link de resetare
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Ți-ai amintit parola? <a href="/conectare" class="text-indigo-600 font-medium hover:underline">Conectează-te</a>
            </p>
        </div>

        <?php elseif ($step == 2): ?>
        <!-- Step 2: Email Sent -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-lg animate-fadeInUp text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Verifică-ți emailul</h1>
            <p class="text-gray-500 mb-6">Am trimis un link de resetare a parolei la adresa ta de email. Verifică și folderul Spam.</p>

            <div class="p-4 bg-gray-50 rounded-xl mb-6">
                <p class="text-sm text-gray-600">Nu ai primit emailul?</p>
                <a href="?step=1" class="text-sm text-indigo-600 font-medium hover:underline">Trimite din nou</a>
            </div>

            <a href="/conectare" class="block w-full py-3 border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                Înapoi la conectare
            </a>
        </div>

        <?php elseif ($step == 3): ?>
        <!-- Step 3: Reset Password -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-lg animate-fadeInUp">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Setează parolă nouă</h1>
                <p class="text-gray-500">Alege o parolă puternică pentru contul tău.</p>
            </div>

            <form action="?step=4" method="GET" class="space-y-4">
                <input type="hidden" name="step" value="4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Parolă nouă</label>
                    <input type="password" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="Minim 8 caractere">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirmă parola</label>
                    <input type="password" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="Repetă parola">
                </div>

                <button type="submit" class="w-full py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                    Resetează parola
                </button>
            </form>
        </div>

        <?php else: ?>
        <!-- Step 4: Success -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-lg animate-fadeInUp text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Parolă resetată!</h1>
            <p class="text-gray-500 mb-6">Parola ta a fost schimbată cu succes. Acum te poți conecta.</p>

            <a href="/conectare" class="block w-full py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                Conectează-te
            </a>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer Mini -->
<footer class="bg-white border-t border-gray-200 py-6">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 text-center text-sm text-gray-500">
        © <?= date('Y') ?> TICS.ro • <a href="/termeni" class="hover:text-gray-900">Termeni</a> • <a href="/confidentialitate" class="hover:text-gray-900">Confidențialitate</a>
    </div>
</footer>

</body>
</html>
