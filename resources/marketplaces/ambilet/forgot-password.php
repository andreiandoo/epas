<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Resetare Parolă';
$pageDescription = 'Resetează parola contului tău';
$bodyClass = 'min-h-screen flex bg-surface';
require_once __DIR__ . '/includes/head.php';
?>
<body class="flex min-h-screen">
    <!-- Left Side - Branding -->
    <div class="relative hidden overflow-hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary via-primary-dark to-secondary bg-pattern">
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
                <h1 class="mb-4 text-4xl font-bold">Ai uitat parola?</h1>
                <p class="mb-8 text-lg text-white/80">Nu-ți face griji! Se întâmplă. Te vom ajuta să îți resetezi parola în câțiva pași simpli.</p>

                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full bg-white/20">1</div>
                        <span class="text-white/90">Introdu adresa de email</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full bg-white/20">2</div>
                        <span class="text-white/90">Verifică inbox-ul pentru link</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full bg-white/20">3</div>
                        <span class="text-white/90">Setează noua parolă</span>
                    </div>
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
                <!-- Initial State - Request Form -->
                <div id="requestForm">
                    <a href="/autentificare" class="inline-flex items-center gap-2 mb-8 text-sm text-muted hover:text-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Înapoi la autentificare
                    </a>

                    <div class="mb-8 text-center">
                        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-primary/10 rounded-2xl">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h2 class="mb-2 text-2xl font-bold lg:text-3xl text-secondary">Resetare parolă</h2>
                        <p class="text-muted">Introdu adresa de email asociată contului tău și îți vom trimite un link de resetare.</p>
                    </div>

                    <form onsubmit="ForgotPage.submit(event)" class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-secondary">Email</label>
                            <input type="email" id="emailInput" placeholder="nume@email.com" required class="w-full px-4 py-3 text-sm transition-all bg-white border input-focus border-border rounded-xl focus:outline-none">
                        </div>

                        <button type="submit" id="submitBtn" class="btn-primary w-full py-3.5 text-white font-semibold rounded-xl text-sm">
                            Trimite link de resetare
                        </button>
                    </form>

                    <p class="mt-6 text-sm text-center text-muted">
                        Ți-ai amintit parola?
                        <a href="/autentificare" class="font-semibold text-primary">Autentifică-te</a>
                    </p>
                </div>

                <!-- Success State - Email Sent -->
                <div id="successState" class="hidden">
                    <div class="text-center">
                        <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full email-sent-animation bg-success/10">
                            <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h2 class="mb-2 text-2xl font-bold text-secondary">Verifică-ți email-ul</h2>
                        <p class="mb-2 text-muted">Am trimis instrucțiunile de resetare la:</p>
                        <p id="sentEmail" class="mb-6 font-semibold text-secondary">email@example.com</p>

                        <div class="p-4 mb-6 text-left bg-white border rounded-xl border-border">
                            <p class="mb-3 text-sm text-muted">Nu ai primit email-ul? Verifică:</p>
                            <ul class="space-y-2 text-sm text-muted">
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Folderul Spam sau Junk
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Adresa de email este corectă
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Poate dura câteva minute
                                </li>
                            </ul>
                        </div>

                        <button onclick="ForgotPage.resend()" id="resendBtn" class="w-full py-3 mb-3 text-sm font-medium transition-colors bg-white border border-border text-secondary rounded-xl hover:bg-primary/10 hover:text-primary hover:border-primary">
                            Retrimite email-ul
                        </button>

                        <a href="/autentificare" class="block w-full py-3 text-sm font-medium text-center text-primary">
                            Înapoi la autentificare
                        </a>
                    </div>
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
    .bg-pattern {
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .input-focus:focus { border-color: var(--color-primary, #A51C30); box-shadow: 0 0 0 3px rgba(165, 28, 48, 0.1); }
    .email-sent-animation { animation: float 3s ease-in-out infinite; }
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
</style>
SCRIPTS;
$scriptsExtra .= '<script defer src="' . asset('assets/js/pages/forgot-password.js') . '"></script>';

require_once __DIR__ . '/includes/scripts.php';
