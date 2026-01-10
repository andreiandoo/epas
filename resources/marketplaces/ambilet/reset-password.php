<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Setează Parolă Nouă';
$pageDescription = 'Setează o parolă nouă pentru contul tău';
require_once __DIR__ . '/includes/head.php';
?>
<body class="min-h-screen flex">
    <!-- Left Side - Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary via-primary-dark to-secondary bg-pattern relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-20 left-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-accent/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 flex flex-col justify-between p-12 text-white">
            <div>
                <a href="/" class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <span class="text-2xl font-extrabold"><?= SITE_NAME ?></span>
                </a>
            </div>

            <div>
                <h1 class="text-4xl font-bold mb-4">Aproape gata!</h1>
                <p class="text-lg text-white/80 mb-8">Setează o parolă nouă pentru contul tău și vei putea accesa din nou toate funcționalitățile.</p>

                <div class="bg-white/10 backdrop-blur rounded-xl p-5">
                    <p class="font-medium mb-3">Sfaturi pentru o parolă sigură:</p>
                    <ul class="space-y-2 text-white/80 text-sm">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Minim 8 caractere
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Litere mari și mici
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Cel puțin o cifră
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Un caracter special (!@#$%)
                        </li>
                    </ul>
                </div>
            </div>

            <div class="text-sm text-white/60">
                © <?= date('Y') ?> <?= SITE_NAME ?>. Toate drepturile rezervate.
            </div>
        </div>
    </div>

    <!-- Right Side - Form -->
    <div class="flex-1 flex flex-col bg-surface">
        <!-- Mobile Header -->
        <div class="lg:hidden p-4 border-b border-border bg-white">
            <a href="/" class="flex items-center gap-2">
                <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <span class="text-xl font-extrabold text-secondary"><?= SITE_NAME ?></span>
            </a>
        </div>

        <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
            <div class="w-full max-w-md">
                <!-- Reset Form -->
                <div id="resetForm">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </div>
                        <h2 class="text-2xl lg:text-3xl font-bold text-secondary mb-2">Setează parolă nouă</h2>
                        <p class="text-muted">Introdu noua parolă pentru contul tău</p>
                    </div>

                    <form onsubmit="ResetPage.submit(event)" class="space-y-4">
                        <input type="hidden" id="token">
                        <input type="hidden" id="email">

                        <div>
                            <label class="block text-sm font-medium text-secondary mb-2">Parolă nouă</label>
                            <div class="relative">
                                <input type="password" id="password" oninput="ResetPage.checkStrength(this.value)" placeholder="Minim 8 caractere" required class="input-focus w-full px-4 py-3 bg-white border border-border rounded-xl text-sm focus:outline-none transition-all pr-12">
                                <button type="button" onclick="ResetPage.togglePassword('password', 'eyeIcon1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-secondary">
                                    <svg id="eyeIcon1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <!-- Password Strength -->
                            <div class="mt-2">
                                <div class="flex gap-1 mb-1">
                                    <div id="str1" class="strength-bar h-1.5 flex-1 bg-border rounded-full"></div>
                                    <div id="str2" class="strength-bar h-1.5 flex-1 bg-border rounded-full"></div>
                                    <div id="str3" class="strength-bar h-1.5 flex-1 bg-border rounded-full"></div>
                                    <div id="str4" class="strength-bar h-1.5 flex-1 bg-border rounded-full"></div>
                                </div>
                                <p id="strengthText" class="text-xs text-muted">Introdu o parolă</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-secondary mb-2">Confirmă parola nouă</label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" oninput="ResetPage.checkMatch()" placeholder="Reintrodu parola" required class="input-focus w-full px-4 py-3 bg-white border border-border rounded-xl text-sm focus:outline-none transition-all pr-12">
                                <button type="button" onclick="ResetPage.togglePassword('confirmPassword', 'eyeIcon2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-secondary">
                                    <svg id="eyeIcon2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <p id="matchText" class="text-xs text-muted mt-1 hidden"></p>
                        </div>

                        <button type="submit" id="submitBtn" class="btn-primary w-full py-3.5 text-white font-semibold rounded-xl text-sm mt-6">
                            Salvează parola nouă
                        </button>
                    </form>

                    <p class="text-center text-sm text-muted mt-6">
                        <a href="/login" class="text-primary font-semibold">Înapoi la autentificare</a>
                    </p>
                </div>

                <!-- Success State -->
                <div id="successState" class="hidden text-center">
                    <div class="success-animation">
                        <div class="w-20 h-20 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-secondary mb-2">Parolă schimbată!</h2>
                    <p class="text-muted mb-8">Parola ta a fost actualizată cu succes. Poți acum să te autentifici cu noua parolă.</p>

                    <a href="/login" class="btn-primary inline-block w-full py-3.5 text-white font-semibold rounded-xl text-sm text-center">
                        Mergi la autentificare
                    </a>

                    <p class="text-sm text-muted mt-6">
                        Vei fi redirecționat automat în <span id="countdown" class="font-bold text-primary">5</span> secunde
                    </p>
                </div>

                <!-- Expired Link State -->
                <div id="expiredState" class="hidden text-center">
                    <div class="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>

                    <h2 class="text-2xl font-bold text-secondary mb-2">Link expirat</h2>
                    <p class="text-muted mb-8">Link-ul de resetare a expirat sau a fost deja folosit. Te rugăm să soliciți un nou link.</p>

                    <a href="/forgot-password" class="btn-primary inline-block w-full py-3.5 text-white font-semibold rounded-xl text-sm text-center mb-3">
                        Solicită link nou
                    </a>
                    <a href="/login" class="block w-full py-3 text-center text-primary font-medium text-sm">
                        Înapoi la autentificare
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-border lg:hidden bg-white">
            <p class="text-xs text-center text-muted">© <?= date('Y') ?> <?= SITE_NAME ?>. Toate drepturile rezervate.</p>
        </div>
    </div>

<?php
$scriptsExtra = <<<'SCRIPTS'
<style>
    .bg-pattern {
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .input-focus:focus { border-color: var(--color-primary, #A51C30); box-shadow: 0 0 0 3px rgba(165, 28, 48, 0.1); }
    .strength-bar { transition: width 0.3s ease, background 0.3s ease; }
    .success-animation { animation: success-pop 0.6s ease-out forwards; }
    @keyframes success-pop {
        0% { transform: scale(0); opacity: 0; }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

<script>
const ResetPage = {
    init() {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const email = urlParams.get('email');

        if (!token || !email) {
            document.getElementById('resetForm').classList.add('hidden');
            document.getElementById('expiredState').classList.remove('hidden');
            return;
        }

        document.getElementById('token').value = token;
        document.getElementById('email').value = email;
    },

    togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    },

    checkStrength(password) {
        const str1 = document.getElementById('str1');
        const str2 = document.getElementById('str2');
        const str3 = document.getElementById('str3');
        const str4 = document.getElementById('str4');
        const text = document.getElementById('strengthText');

        // Reset
        [str1, str2, str3, str4].forEach(el => el.style.background = '#E2E8F0');

        if (password.length === 0) {
            text.textContent = 'Introdu o parolă';
            text.className = 'text-xs text-muted';
            return;
        }

        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;

        const colors = {
            1: '#EF4444',
            2: '#F59E0B',
            3: '#10B981',
            4: '#10B981'
        };

        const texts = {
            1: 'Slabă',
            2: 'Medie',
            3: 'Puternică',
            4: 'Foarte puternică'
        };

        const textColors = {
            1: 'text-error',
            2: 'text-warning',
            3: 'text-success',
            4: 'text-success'
        };

        if (strength >= 1) str1.style.background = colors[strength];
        if (strength >= 2) str2.style.background = colors[strength];
        if (strength >= 3) str3.style.background = colors[strength];
        if (strength >= 4) str4.style.background = colors[strength];

        text.textContent = texts[strength] || 'Foarte slabă';
        text.className = `text-xs ${textColors[strength] || 'text-error'}`;

        // Also check match
        this.checkMatch();
    },

    checkMatch() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirmPassword').value;
        const matchText = document.getElementById('matchText');

        if (confirm.length === 0) {
            matchText.classList.add('hidden');
            return;
        }

        matchText.classList.remove('hidden');

        if (password === confirm) {
            matchText.textContent = '✓ Parolele coincid';
            matchText.className = 'text-xs text-success mt-1';
        } else {
            matchText.textContent = '✗ Parolele nu coincid';
            matchText.className = 'text-xs text-error mt-1';
        }
    },

    async submit(event) {
        event.preventDefault();

        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirmPassword').value;
        const token = document.getElementById('token').value;
        const email = document.getElementById('email').value;
        const btn = document.getElementById('submitBtn');

        if (password !== confirm) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Parolele nu coincid!');
            }
            return;
        }

        if (password.length < 8) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Parola trebuie să aibă minim 8 caractere!');
            }
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Se salvează...';

        try {
            const response = await AmbiletAPI.post('/customer/reset-password', {
                token,
                email,
                password,
                password_confirmation: confirm
            });

            if (response.success !== false) {
                document.getElementById('resetForm').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');

                // Countdown
                let countdown = 5;
                const countdownEl = document.getElementById('countdown');

                const timer = setInterval(() => {
                    countdown--;
                    countdownEl.textContent = countdown;

                    if (countdown <= 0) {
                        clearInterval(timer);
                        window.location.href = '/login';
                    }
                }, 1000);
            } else {
                if (response.message && response.message.includes('token')) {
                    document.getElementById('resetForm').classList.add('hidden');
                    document.getElementById('expiredState').classList.remove('hidden');
                } else {
                    if (typeof AmbiletNotifications !== 'undefined') {
                        AmbiletNotifications.error(response.message || 'A apărut o eroare. Încearcă din nou.');
                    }
                    btn.disabled = false;
                    btn.textContent = 'Salvează parola nouă';
                }
            }
        } catch (error) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('A apărut o eroare. Te rugăm să încerci din nou.');
            }
            btn.disabled = false;
            btn.textContent = 'Salvează parola nouă';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ResetPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
