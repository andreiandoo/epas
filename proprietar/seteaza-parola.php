<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Setează Parolă — Administrare Locație';
$pageDescription = 'Setează parola pentru contul tău de administrare locație';
$cssBundle = 'auth';
$bodyClass = 'flex min-h-screen';
require_once __DIR__ . '/../includes/head.php';
?>
    <!-- Left Side - Branding -->
    <div class="relative hidden overflow-hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary via-primary-dark to-secondary">
        <div class="absolute inset-0">
            <div class="absolute w-64 h-64 rounded-full top-20 left-20 bg-white/5 blur-3xl"></div>
            <div class="absolute rounded-full bottom-20 right-20 w-96 h-96 bg-accent/10 blur-3xl"></div>
        </div>

        <div class="relative z-10 flex flex-col justify-between p-12 text-white">
            <div>
                <a href="/" class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 backdrop-blur rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <span class="text-2xl font-extrabold"><?= SITE_NAME ?></span>
                </a>
            </div>

            <div>
                <h1 class="mb-4 text-4xl font-bold">Bine ai venit!</h1>
                <p class="mb-8 text-lg text-white/80">Setează-ți parola și vei putea administra locațiile, vânzările, POS-ul, rapoartele și echipa ta.</p>

                <div class="p-5 bg-white/10 backdrop-blur rounded-xl">
                    <p class="mb-3 font-medium">Sfaturi pentru o parolă sigură:</p>
                    <ul class="space-y-2 text-sm text-white/80">
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

            <div class="text-sm text-white/90">
                © <?= date('Y') ?> <?= SITE_NAME ?>. Toate drepturile rezervate.
            </div>
        </div>
    </div>

    <!-- Right Side - Form -->
    <div class="flex flex-col flex-1 bg-surface">
        <!-- Mobile Header -->
        <div class="p-4 bg-white border-b lg:hidden border-border">
            <a href="/" class="flex items-center gap-2">
                <div class="flex items-center justify-center w-10 h-10 bg-primary rounded-xl">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <span class="text-xl font-extrabold text-secondary"><?= SITE_NAME ?></span>
            </a>
        </div>

        <div class="flex items-center justify-center flex-1 p-6 lg:p-12">
            <div class="w-full max-w-md">
                <!-- Set Password Form -->
                <div id="resetForm">
                    <div class="mb-8 text-center">
                        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-primary/10 rounded-2xl">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </div>
                        <h2 class="mb-2 text-2xl font-bold lg:text-3xl text-secondary">Setează-ți parola</h2>
                        <p class="text-muted">Alege o parolă pentru contul de administrare locație</p>
                    </div>

                    <form onsubmit="SetPasswordPage.submit(event)" class="space-y-4">
                        <input type="hidden" id="token">
                        <input type="hidden" id="email">

                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary">Parolă</label>
                            <div class="relative">
                                <input type="password" id="password" oninput="SetPasswordPage.checkStrength(this.value)" placeholder="Minim 8 caractere" required class="w-full px-4 py-3 pr-12 text-sm transition-all bg-white border input-focus border-border rounded-xl focus:outline-none">
                                <button type="button" onclick="SetPasswordPage.togglePassword('password', 'eyeIcon1')" class="absolute -translate-y-1/2 right-3 top-1/2 text-muted hover:text-secondary">
                                    <svg id="eyeIcon1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
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
                            <label class="block mb-2 text-sm font-medium text-secondary">Confirmă parola</label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" oninput="SetPasswordPage.checkMatch()" placeholder="Reintrodu parola" required class="w-full px-4 py-3 pr-12 text-sm transition-all bg-white border input-focus border-border rounded-xl focus:outline-none">
                                <button type="button" onclick="SetPasswordPage.togglePassword('confirmPassword', 'eyeIcon2')" class="absolute -translate-y-1/2 right-3 top-1/2 text-muted hover:text-secondary">
                                    <svg id="eyeIcon2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <p id="matchText" class="hidden mt-1 text-xs text-muted"></p>
                        </div>

                        <button type="submit" id="submitBtn" class="btn-primary w-full py-3.5 text-white font-semibold rounded-xl text-sm mt-6 bg-primary">
                            Setează parola
                        </button>
                    </form>

                    <p class="mt-6 text-sm text-center text-muted">
                        <a href="/organizator/login" class="font-semibold text-primary">Înapoi la autentificare</a>
                    </p>
                </div>

                <!-- Success State -->
                <div id="successState" class="hidden text-center">
                    <div class="success-animation">
                        <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-success/10">
                            <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>

                    <h2 class="mb-2 text-2xl font-bold text-secondary">Parolă setată!</h2>
                    <p class="mb-8 text-muted">Parola ta a fost salvată cu succes. Te poți autentifica acum în contul de administrare locație.</p>

                    <a href="/organizator/login" class="btn-primary inline-block w-full py-3.5 text-white font-semibold rounded-xl text-sm text-center bg-primary">
                        Mergi la autentificare
                    </a>

                    <p class="mt-6 text-sm text-muted">
                        Vei fi redirecționat automat în <span id="countdown" class="font-bold text-primary">5</span> secunde
                    </p>
                </div>

                <!-- Expired Link State -->
                <div id="expiredState" class="hidden text-center">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-error/10">
                        <svg class="w-10 h-10 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>

                    <h2 class="mb-2 text-2xl font-bold text-secondary">Link invalid sau expirat</h2>
                    <p class="mb-8 text-muted">Link-ul de setare a parolei este invalid, a expirat (valabil 48 de ore) sau a fost deja folosit. Contactează administratorul AmBilet pentru un link nou.</p>

                    <a href="/organizator/login" class="btn-primary bg-primary inline-block w-full py-3.5 text-white font-semibold rounded-xl text-sm text-center">
                        Înapoi la autentificare
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-4 bg-white border-t border-border lg:hidden">
            <p class="text-xs text-center text-muted">© <?= date('Y') ?> <?= SITE_NAME ?>. Toate drepturile rezervate.</p>
        </div>
    </div>

<?php
$scriptsExtra = <<<'SCRIPTS'
<style>
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
const SetPasswordPage = {
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
        const bars = ['str1', 'str2', 'str3', 'str4'].map(id => document.getElementById(id));
        const text = document.getElementById('strengthText');
        bars.forEach(el => el.style.background = '#E2E8F0');

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

        const colors = { 1: '#EF4444', 2: '#F59E0B', 3: '#10B981', 4: '#10B981' };
        const texts = { 1: 'Slabă', 2: 'Medie', 3: 'Puternică', 4: 'Foarte puternică' };
        const textColors = { 1: 'text-error', 2: 'text-warning', 3: 'text-success', 4: 'text-success' };

        for (var i = 0; i < strength; i++) { bars[i].style.background = colors[strength]; }
        text.textContent = texts[strength] || 'Foarte slabă';
        text.className = 'text-xs ' + (textColors[strength] || 'text-error');
        this.checkMatch();
    },

    checkMatch() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirmPassword').value;
        const matchText = document.getElementById('matchText');

        if (confirm.length === 0) { matchText.classList.add('hidden'); return; }
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
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('Parolele nu coincid!');
            return;
        }
        if (password.length < 8) {
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('Parola trebuie să aibă minim 8 caractere!');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Se salvează...';

        try {
            const response = await AmbiletAPI.post('/venue-owner/set-password', {
                token: token,
                email: email,
                password: password,
                password_confirmation: confirm
            });

            if (response.success !== false) {
                document.getElementById('resetForm').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');
                let countdown = 5;
                const countdownEl = document.getElementById('countdown');
                const timer = setInterval(() => {
                    countdown--;
                    countdownEl.textContent = countdown;
                    if (countdown <= 0) { clearInterval(timer); window.location.href = '/organizator/login'; }
                }, 1000);
            } else {
                var msg = (response.message || '').toLowerCase();
                if (msg.includes('link') || msg.includes('token') || msg.includes('expir')) {
                    document.getElementById('resetForm').classList.add('hidden');
                    document.getElementById('expiredState').classList.remove('hidden');
                } else {
                    if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(response.message || 'A apărut o eroare. Încearcă din nou.');
                    btn.disabled = false;
                    btn.textContent = 'Setează parola';
                }
            }
        } catch (error) {
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('A apărut o eroare. Te rugăm să încerci din nou.');
            btn.disabled = false;
            btn.textContent = 'Setează parola';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => SetPasswordPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/../includes/scripts.php';
