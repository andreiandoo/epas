<?php
/**
 * Artist Account — Email Verification
 * Two modes:
 *   1. With ?token=...&email=... → auto-verifies on load (link from email)
 *   2. Without params → "Verifică emailul" prompt + Resend button
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Cont artist — verificare email';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Verifică-ți emailul';
$authSubtitle = 'Confirmă adresa de email pentru ca cererea ta de cont artist să intre în review.';

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">

            <!-- Auto-verify spinner (when token in URL) -->
            <div id="verify-processing" class="hidden p-8 text-center bg-white border rounded-2xl border-border">
                <div class="w-12 h-12 mx-auto mb-4 border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <h2 class="text-xl font-bold text-secondary">Se verifică emailul…</h2>
                <p class="mt-2 text-muted">Te rugăm să aștepți câteva secunde.</p>
            </div>

            <!-- Verification result (success/fail) -->
            <div id="verify-result" class="hidden p-8 bg-white border rounded-2xl border-border">
                <div id="verify-result-content" class="text-center"></div>
            </div>

            <!-- Default state — "Verifică-ți emailul" prompt -->
            <div id="verify-pending" class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-6 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-primary/10">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Verifică-ți emailul</h2>
                    <p class="mt-2 text-muted" id="verify-email-text">
                        Ți-am trimis un link de verificare. Dă click pe el pentru a continua.
                    </p>
                </div>

                <div class="p-4 mb-6 rounded-xl bg-surface">
                    <div class="flex items-start gap-3">
                        <svg class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-sm text-muted">
                            <p class="mb-1 font-medium text-secondary">Nu ai primit emailul?</p>
                            <p>Verifică folderul <strong>Spam</strong> sau <strong>Promoții</strong>. Poate dura câteva minute.</p>
                        </div>
                    </div>
                </div>

                <button id="resend-btn" class="w-full mb-3 btn btn-primary bg-primary btn-lg">
                    Retrimite emailul de verificare
                </button>

                <a href="/artist/login" class="block w-full text-center btn btn-lg bg-surface text-secondary hover:bg-primary/10 hover:text-primary">
                    Înapoi la autentificare
                </a>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-verify-email.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
