<?php
/**
 * TICS.ro - Login Page
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Conectare';
$pageDescription = 'Conectează-te la contul tău TICS pentru a accesa biletele și preferințele tale.';
$hideCategoriesBar = true;
$bodyClass = 'bg-gray-50';

$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
    .form-input { transition: all 0.2s ease; }
    .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
    .social-btn { transition: all 0.2s ease; }
    .social-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
</style>
HTML;

include __DIR__ . '/includes/head.php';
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
            <a href="/evenimente" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">← Înapoi la evenimente</a>
        </div>
    </div>
</header>

<main class="min-h-[calc(100vh-180px)] flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-lg animate-fadeInUp">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Bine ai revenit!</h1>
                <p class="text-gray-500">Conectează-te pentru a continua</p>
            </div>

            <!-- Social Login -->
            <div class="space-y-3 mb-6">
                <button class="social-btn w-full flex items-center justify-center gap-3 px-4 py-3 border border-gray-200 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    Continuă cu Google
                </button>
                <button class="social-btn w-full flex items-center justify-center gap-3 px-4 py-3 border border-gray-200 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Continuă cu Facebook
                </button>
            </div>

            <div class="relative mb-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white text-gray-500">sau cu email</span>
                </div>
            </div>

            <!-- Login Form -->
            <form action="#" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="email@exemplu.com">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">Parolă</label>
                        <a href="/parola-uitata" class="text-sm text-indigo-600 hover:underline">Ai uitat parola?</a>
                    </div>
                    <div class="relative">
                        <input type="password" required id="password" class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none pr-12" placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-600">Ține-mă minte</span>
                </label>

                <button type="submit" class="w-full py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                    Conectare
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Nu ai cont? <a href="/inregistrare" class="text-indigo-600 font-medium hover:underline">Înregistrează-te gratuit</a>
            </p>
        </div>

        <!-- Benefits -->
        <div class="mt-6 grid grid-cols-3 gap-4 text-center">
            <div class="p-4 bg-white rounded-xl border border-gray-200">
                <div class="text-2xl mb-2">🎫</div>
                <p class="text-xs text-gray-600">Biletele tale</p>
            </div>
            <div class="p-4 bg-white rounded-xl border border-gray-200">
                <div class="text-2xl mb-2">🎁</div>
                <p class="text-xs text-gray-600">Puncte bonus</p>
            </div>
            <div class="p-4 bg-white rounded-xl border border-gray-200">
                <div class="text-2xl mb-2">⚡</div>
                <p class="text-xs text-gray-600">AI personalizat</p>
            </div>
        </div>
    </div>
</main>

<!-- Footer Mini -->
<footer class="bg-white border-t border-gray-200 py-6">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 text-center text-sm text-gray-500">
        © <?= date('Y') ?> TICS.ro • <a href="/termeni" class="hover:text-gray-900">Termeni</a> • <a href="/confidentialitate" class="hover:text-gray-900">Confidențialitate</a>
    </div>
</footer>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    }
</script>

</body>
</html>
