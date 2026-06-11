<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Email Confirmat';
$bodyClass = 'min-h-screen flex items-center justify-center bg-surface p-4';
require_once __DIR__ . '/includes/head.php';
?>
    <div class="w-full max-w-md text-center">
        <div class="mb-8">
            <a href="/" class="inline-flex items-center gap-3">
                <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <span class="text-2xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
            </a>
        </div>

        <div id="success-card" class="bg-white rounded-2xl border border-border p-8 shadow-sm">
            <div class="w-20 h-20 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-secondary mb-3">Email confirmat!</h1>
            <p class="text-muted mb-6">Contul tau a fost activat cu succes. Acum te poti autentifica si poti incepe sa explorezi evenimente.</p>
            <div class="space-y-3">
                <a href="/login.php" class="btn btn-primary w-full">
                    Autentifica-te
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="/" class="btn btn-secondary w-full">Exploreaza evenimente</a>
            </div>
            <div class="mt-8 pt-6 border-t border-border">
                <p class="text-sm text-muted mb-4">Ce poti face acum:</p>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs text-muted">Cauta evenimente</span>
                    </div>
                    <div>
                        <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <span class="text-xs text-muted">Cumpara bilete</span>
                    </div>
                    <div>
                        <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs text-muted">Acumulezi puncte</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="error-card" class="hidden bg-white rounded-2xl border border-border p-8 shadow-sm">
            <div class="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-secondary mb-3">Link invalid</h1>
            <p class="text-muted mb-6">Acest link de confirmare nu este valid sau a expirat. Daca te-ai inregistrat recent, verifica-ti email-ul pentru un link nou.</p>
            <div class="space-y-3">
                <a href="/login.php" class="btn btn-primary w-full">Incearca sa te autentifici</a>
                <a href="/register.php" class="btn btn-secondary w-full">Creeaza cont nou</a>
            </div>
        </div>

        <div id="already-confirmed-card" class="hidden bg-white rounded-2xl border border-border p-8 shadow-sm">
            <div class="w-20 h-20 bg-accent/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-secondary mb-3">Deja confirmat</h1>
            <p class="text-muted mb-6">Acest email a fost deja confirmat. Te poti autentifica cu contul tau.</p>
            <a href="/login.php" class="btn btn-primary w-full">Autentifica-te</a>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
if (status === 'error' || status === 'invalid') {
    document.getElementById('success-card').classList.add('hidden');
    document.getElementById('error-card').classList.remove('hidden');
} else if (status === 'already_confirmed') {
    document.getElementById('success-card').classList.add('hidden');
    document.getElementById('already-confirmed-card').classList.remove('hidden');
}
</script>
JS;
require_once __DIR__ . '/includes/scripts.php';
?>
