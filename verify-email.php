<?php
/**
 * Email Verification Pending / Verification Handler Page
 * Shows "check your email" message after registration,
 * or processes email verification token from link.
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Verificare email';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Verificare email';
$authSubtitle = 'Contul tău este aproape gata! Verifică-ți emailul pentru a activa toate funcționalitățile.';
$authFeatures = [
    ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'text' => 'Verifică inbox-ul sau spam'],
    ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Un singur click pentru activare'],
    ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'text' => 'Contul tău este securizat'],
];

$cssBundle = 'auth';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center lg:hidden">
                <a href="/" class="inline-flex items-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 bg-primary rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
                </a>
            </div>

            <!-- Verification Token Processing (hidden, shown by JS when token present) -->
            <div id="verify-processing" class="hidden p-8 bg-white border rounded-2xl border-border">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-4 border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                    <h2 class="text-xl font-bold text-secondary">Se verifică emailul...</h2>
                    <p class="mt-2 text-muted">Te rugăm să aștepți.</p>
                </div>
            </div>

            <!-- Verification Result (shown by JS) -->
            <div id="verify-result" class="hidden p-8 bg-white border rounded-2xl border-border">
                <div class="text-center" id="verify-result-content"></div>
            </div>

            <!-- Check Your Email (default view after registration) -->
            <div id="verify-pending" class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-6 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-primary/10">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Verifică-ți emailul</h2>
                    <p class="mt-2 text-muted" id="verify-email-text">
                        Ți-am trimis un email de verificare. Dă click pe linkul din email pentru a activa contul.
                    </p>
                </div>

                <div class="p-4 mb-6 rounded-xl bg-surface">
                    <div class="flex items-start gap-3">
                        <svg class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-sm text-muted">
                            <p class="mb-1 font-medium text-secondary">Nu ai primit emailul?</p>
                            <p>Verifică folderul <strong>Spam</strong> sau <strong>Promoții</strong>. Emailul poate dura câteva minute.</p>
                        </div>
                    </div>
                </div>

                <button id="resend-btn" class="w-full mb-3 btn btn-primary bg-primary btn-lg" onclick="resendVerification()">
                    Retrimite emailul de verificare
                </button>

                <a href="/cont" class="block w-full text-center btn btn-lg bg-surface text-secondary hover:bg-primary/10 hover:text-primary">
                    Mergi la contul tău
                </a>

                <p class="mt-4 text-xs text-center text-muted">
                    Poți folosi contul și fără verificare, dar unele funcționalități necesită email verificat.
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/verify-email.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
?>
