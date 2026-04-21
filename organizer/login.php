<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Autentificare Organizator';
$bodyClass = 'min-h-screen flex bg-surface';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
?>
    <div class="relative hidden overflow-hidden lg:flex lg:w-1/2 bg-gradient-to-br from-secondary via-secondary to-primary-dark">
        <div class="absolute inset-0">
            <div class="absolute w-64 h-64 rounded-full top-20 left-20 bg-white/5 blur-3xl"></div>
            <div class="absolute rounded-full bottom-20 right-20 w-96 h-96 bg-primary/10 blur-3xl"></div>
        </div>
        <div class="relative z-10 flex flex-col justify-between p-12 text-white">
            <div>
                <a href="/" class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 backdrop-blur rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <span class="text-2xl font-extrabold"><?= strtoupper(SITE_NAME) ?></span>
                </a>
                <span class="inline-block px-3 py-1 mt-2 text-sm rounded-full bg-primary/30">Portal Organizatori</span>
            </div>
            <div>
                <h1 class="mb-4 text-4xl font-bold">Gestioneaza-ti evenimentele</h1>
                <p class="mb-8 text-lg text-white/80">Acceseaza dashboard-ul pentru a vedea vanzarile, gestiona participantii si analiza datele.</p>
                <div class="space-y-4">
                    <div class="flex items-center gap-3"><div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div><span class="text-white/90">Rapoarte in timp real</span></div>
                    <div class="flex items-center gap-3"><div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg></div><span class="text-white/90">Gestionare participanti</span></div>
                    <div class="flex items-center gap-3"><div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-white/90">Plati si facturi</span></div>
                </div>
            </div>
            <div class="text-sm text-white/90">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Portal Organizatori.</div>
        </div>
    </div>

    <div class="flex flex-col flex-1">
        <div class="p-4 bg-white border-b lg:hidden border-border">
            <a href="/" class="flex items-center gap-2">
                <div class="flex items-center justify-center w-10 h-10 bg-secondary rounded-xl"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>
                <div><span class="text-xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span><span class="block text-xs text-muted">Organizatori</span></div>
            </a>
        </div>
        <div class="flex items-center justify-center flex-1 p-6 lg:p-12">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <h2 class="mb-2 text-2xl font-bold lg:text-3xl text-secondary">Autentificare Organizator</h2>
                    <p class="text-muted">Introdu datele pentru a accesa panoul</p>
                </div>
                <div id="error-message" class="hidden p-4 mb-6 text-sm border border-red-200 bg-red-50 rounded-xl text-error"></div>
                <form id="login-form" class="space-y-4">
                    <div>
                        <label class="label">Email</label>
                        <input type="email" name="email" required placeholder="organizator@email.com" class="input" autocomplete="email">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="mb-0 label">Parola</label>
                            <a href="/organizator/forgot-password" class="text-sm font-medium text-primary">Ai uitat parola?</a>
                        </div>
                        <div class="relative">
                            <input type="password" name="password" required placeholder="********" class="pr-12 input" id="password-input" autocomplete="current-password">
                            <button type="button" onclick="togglePassword()" class="absolute -translate-y-1/2 right-4 top-1/2 text-muted hover:text-secondary">
                                <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                            <span class="text-sm text-muted">Tine-ma minte</span>
                        </label>
                    </div>
                    <button type="submit" class="w-full btn btn-primary bg-primary" id="submit-btn">
                        <span id="btn-text">Autentificare</span>
                        <div id="btn-spinner" class="hidden spinner"></div>
                    </button>
                </form>
                <div class="relative my-8"><div class="absolute inset-0 flex items-center"><div class="w-full border-t border-border"></div></div><div class="relative flex justify-center text-sm"><span class="px-4 bg-surface text-muted">sau</span></div></div>
                <p class="text-center text-muted">Nu ai cont de organizator? <a href="/organizator/inregistrare" class="font-semibold text-primary">Inregistreaza-te</a></p>
                <div class="mt-8 text-center"><a href="/autentificare" class="text-sm text-muted hover:text-primary">&larr; Inapoi la autentificarea clienti</a></div>
            </div>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
function togglePassword() {
    const input = document.getElementById('password-input');
    const icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    }
}
document.addEventListener('DOMContentLoaded', function() {
if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isOrganizer()) { window.location.href = '/organizator/events'; }
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('submit-btn');
    const btnText = document.getElementById('btn-text');
    const btnSpinner = document.getElementById('btn-spinner');
    const errorDiv = document.getElementById('error-message');
    submitBtn.disabled = true; btnText.classList.add('hidden'); btnSpinner.classList.remove('hidden'); errorDiv.classList.add('hidden');
    try {
        const result = await AmbiletAuth.loginOrganizer(form.email.value, form.password.value);
        if (result.success) {
            AmbiletNotifications.success('Autentificare reusita!');
            setTimeout(() => { window.location.href = '/organizator/events'; }, 500);
        } else {
            errorDiv.textContent = result.message || 'Autentificare esuata. Verifica email-ul si parola.';
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false; btnText.classList.remove('hidden'); btnSpinner.classList.add('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'A aparut o eroare. Te rugam sa incerci din nou.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false; btnText.classList.remove('hidden'); btnSpinner.classList.add('hidden');
    }
});
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
