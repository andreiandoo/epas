<?php
/**
 * TICS.ro - Registration Page
 * Split-screen layout with form and visual panel
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Creează cont';
$pageDescription = 'Creează un cont TICS pentru a accesa bilete, puncte bonus și recomandări personalizate AI.';
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

    .register-btn { transition: all 0.3s ease; }
    .register-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.5); }

    .social-btn { transition: all 0.2s ease; }
    .social-btn:hover { transform: translateY(-2px); }

    .strength-bar { transition: all 0.3s ease; }
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<div class="min-h-screen flex">
    <!-- Left side - Form -->
    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-md">
            <a href="/" class="flex items-center gap-2 mb-8 animate-fadeInUp">
                <div class="w-10 h-10 bg-gray-900 rounded-xl flex items-center justify-center">
                    <span class="text-white font-bold">T</span>
                </div>
                <span class="font-bold text-xl">TICS</span>
            </a>

            <div class="animate-fadeInUp" style="animation-delay: 0.1s">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Creează cont gratuit</h1>
                <p class="text-gray-500 mb-8">Înregistrează-te pentru a cumpăra bilete și a câștiga puncte.</p>
            </div>

            <!-- Social Login -->
            <div class="grid grid-cols-2 gap-3 mb-6 animate-fadeInUp" style="animation-delay: 0.2s">
                <button class="social-btn flex items-center justify-center gap-2 py-3 px-4 bg-white border border-gray-200 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    Google
                </button>
                <button class="social-btn flex items-center justify-center gap-2 py-3 px-4 bg-[#1877F2] text-white rounded-xl font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </button>
            </div>

            <div class="flex items-center gap-4 mb-6 animate-fadeInUp" style="animation-delay: 0.3s">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-sm text-gray-400">sau cu email</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Register Form -->
            <form class="space-y-4 animate-fadeInUp" style="animation-delay: 0.4s" onsubmit="handleRegister(event)">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prenume</label>
                        <input type="text" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="Andrei">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nume</label>
                        <input type="text" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="Popescu">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="andrei@email.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Parolă</label>
                    <input type="password" id="password" required class="form-input w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none" placeholder="Min. 8 caractere" oninput="checkStrength(this.value)">
                    <div class="mt-2 flex gap-1">
                        <div class="strength-bar h-1 flex-1 bg-gray-200 rounded" id="str1"></div>
                        <div class="strength-bar h-1 flex-1 bg-gray-200 rounded" id="str2"></div>
                        <div class="strength-bar h-1 flex-1 bg-gray-200 rounded" id="str3"></div>
                        <div class="strength-bar h-1 flex-1 bg-gray-200 rounded" id="str4"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1" id="strengthText">Minim 8 caractere, o majusculă și o cifră</p>
                </div>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" required class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mt-0.5">
                    <span class="text-sm text-gray-600">Accept <a href="/termeni" class="text-indigo-600 hover:underline">Termenii și Condițiile</a> și <a href="/confidentialitate" class="text-indigo-600 hover:underline">Politica de Confidențialitate</a></span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-600">Vreau să primesc oferte și noutăți pe email</span>
                </label>

                <button type="submit" class="register-btn w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl">
                    Creează cont
                </button>
            </form>

            <!-- Benefits -->
            <div class="mt-8 p-4 bg-green-50 rounded-xl border border-green-100 animate-fadeInUp" style="animation-delay: 0.5s">
                <p class="text-sm font-medium text-green-800 mb-2">🎁 Beneficii la înregistrare:</p>
                <ul class="text-sm text-green-700 space-y-1">
                    <li>✓ 100 puncte cadou de bun venit</li>
                    <li>✓ Acces la oferte exclusive</li>
                    <li>✓ Istoric bilete și comenzi</li>
                </ul>
            </div>

            <p class="mt-6 text-center text-gray-500 animate-fadeInUp" style="animation-delay: 0.6s">
                Ai deja cont? <a href="/conectare" class="text-indigo-600 font-medium hover:underline">Intră în cont</a>
            </p>
        </div>
    </div>

    <!-- Right side - Visual -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-purple-600 via-indigo-600 to-blue-500 relative overflow-hidden">
        <div class="absolute top-20 right-20 w-32 h-32 bg-white/10 rounded-full blur-xl animate-float"></div>
        <div class="absolute bottom-32 left-20 w-40 h-40 bg-white/10 rounded-full blur-xl animate-float" style="animation-delay: 1s"></div>

        <div class="relative z-10 flex flex-col items-center justify-center w-full p-12 text-white">
            <div class="text-center max-w-md">
                <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-8 backdrop-blur animate-float">
                    <span class="text-4xl">🎫</span>
                </div>
                <h2 class="text-3xl font-bold mb-4">Alătură-te comunității</h2>
                <p class="text-white/80 mb-8">Peste 50.000 de fani au descoperit evenimente unice prin TICS.</p>

                <!-- Testimonial -->
                <div class="bg-white/10 backdrop-blur rounded-2xl p-6 text-left">
                    <p class="text-white/90 mb-4">"Am descoperit concerte incredibile și am economisit cu punctele de fidelitate. Recomand!"</p>
                    <div class="flex items-center gap-3">
                        <img src="https://i.pravatar.cc/40?img=1" class="w-10 h-10 rounded-full">
                        <div>
                            <p class="font-medium">Maria P.</p>
                            <p class="text-sm text-white/60">Membru din 2024</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function checkStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
        const texts = ['Foarte slabă', 'Slabă', 'Medie', 'Puternică'];

        for (let i = 1; i <= 4; i++) {
            const bar = document.getElementById('str' + i);
            bar.className = 'strength-bar h-1 flex-1 rounded ' + (i <= strength ? colors[strength - 1] : 'bg-gray-200');
        }

        if (strength > 0) {
            document.getElementById('strengthText').textContent = 'Putere parolă: ' + texts[strength - 1];
        }
    }

    function handleRegister(e) {
        e.preventDefault();
        window.location.href = '/';
    }
</script>

</body>
</html>
