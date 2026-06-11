<?php
/**
 * TICS.ro - Login Page
 * Split-screen layout with form and visual panel
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Autentificare';
$pageDescription = 'Conectează-te la contul tău TICS pentru a accesa biletele și preferințele tale.';
$hideCategoriesBar = true;
$bodyClass = 'bg-gray-50 min-h-screen';

$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
    .animate-float { animation: float 3s ease-in-out infinite; }

    .form-input { transition: all 0.2s ease; }
    .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }

    .login-btn { transition: all 0.3s ease; }
    .login-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.5); }

    .social-btn { transition: all 0.2s ease; }
    .social-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px -5px rgba(0, 0, 0, 0.1); }

    .bg-pattern {
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<div class="min-h-screen flex">
    <!-- Left side - Form -->
    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-2 mb-8 animate-fadeInUp">
                <div class="w-10 h-10 bg-gray-900 rounded-xl flex items-center justify-center">
                    <span class="text-white font-bold">T</span>
                </div>
                <span class="font-bold text-xl">TICS</span>
            </a>

            <div class="animate-fadeInUp" style="animation-delay: 0.1s">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Bine ai revenit!</h1>
                <p class="text-gray-500 mb-8">Intră în cont pentru a-ți vedea biletele și a descoperi evenimente noi.</p>
            </div>

            <!-- Social Login -->
            <div class="space-y-3 mb-6 animate-fadeInUp" style="animation-delay: 0.2s">
                <button class="social-btn w-full flex items-center justify-center gap-3 py-3 px-4 bg-white border border-gray-200 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continuă cu Google
                </button>
                <button class="social-btn w-full flex items-center justify-center gap-3 py-3 px-4 bg-[#1877F2] text-white rounded-xl font-medium hover:bg-[#1565c0]">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Continuă cu Facebook
                </button>
            </div>

            <div class="flex items-center gap-4 mb-6 animate-fadeInUp" style="animation-delay: 0.3s">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-sm text-gray-400">sau cu email</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Login Form -->
            <form class="space-y-5 animate-fadeInUp" style="animation-delay: 0.4s" onsubmit="handleLogin(event)">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="nume@email.com">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Parolă</label>
                        <a href="/parola-uitata" class="text-sm text-indigo-600 hover:underline">Ai uitat parola?</a>
                    </div>
                    <div class="relative">
                        <input type="password" id="password" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none pr-12" placeholder="••••••••">
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
                    <span class="text-sm text-gray-600">Ține-mă minte pe acest dispozitiv</span>
                </label>

                <!-- Demo Info -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm text-indigo-700">
                    <strong>Demo:</strong> alexandru.marin@example.com / demo123
                </div>

                <button type="submit" class="login-btn w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl">
                    Intră în cont
                </button>
            </form>

            <p class="mt-8 text-center text-gray-500 animate-fadeInUp" style="animation-delay: 0.5s">
                Nu ai cont încă? <a href="/inregistrare" class="text-indigo-600 font-medium hover:underline">Creează unul gratuit</a>
            </p>

            <div class="mt-6 pt-6 border-t border-gray-200 text-center animate-fadeInUp" style="animation-delay: 0.6s">
                <p class="text-sm text-gray-500 mb-2">Ești organizator de evenimente?</p>
                <a href="/organizator/conectare" class="inline-flex items-center gap-2 text-indigo-600 font-medium hover:underline">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Portal organizatori →
                </a>
            </div>
        </div>
    </div>

    <!-- Right side - Visual -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 relative overflow-hidden">
        <div class="absolute inset-0 bg-pattern opacity-30"></div>

        <!-- Floating elements -->
        <div class="absolute top-20 left-20 w-32 h-32 bg-white/10 rounded-full blur-xl animate-float"></div>
        <div class="absolute bottom-32 right-20 w-40 h-40 bg-white/10 rounded-full blur-xl animate-float" style="animation-delay: 1s"></div>

        <div class="relative z-10 flex flex-col items-center justify-center w-full p-12 text-white">
            <div class="text-center max-w-md">
                <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-8 backdrop-blur animate-float">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold mb-4">Descoperă evenimente unice</h2>
                <p class="text-white/80 mb-8">Concerte, festivaluri, stand-up comedy și multe altele te așteaptă.</p>

                <div class="flex items-center justify-center gap-4">
                    <div class="text-center">
                        <p class="text-3xl font-bold">500+</p>
                        <p class="text-sm text-white/70">Evenimente</p>
                    </div>
                    <div class="w-px h-12 bg-white/20"></div>
                    <div class="text-center">
                        <p class="text-3xl font-bold">50k+</p>
                        <p class="text-sm text-white/70">Utilizatori</p>
                    </div>
                    <div class="w-px h-12 bg-white/20"></div>
                    <div class="text-center">
                        <p class="text-3xl font-bold">98%</p>
                        <p class="text-sm text-white/70">Satisfacție</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

    function handleLogin(e) {
        e.preventDefault();
        // Simulate login - redirect to user dashboard
        window.location.href = '/cont';
    }
</script>

</body>
</html>
