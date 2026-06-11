<?php
/**
 * Artist Account — Forgot Password
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Cont artist — parolă uitată';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Resetare parolă';
$authSubtitle = 'Introdu adresa de email asociată contului de artist și îți vom trimite un link de resetare.';

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <div id="forgot-form-wrap" class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-8 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-primary/10 rounded-2xl">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Resetează parola</h2>
                    <p class="mt-2 text-muted">Îți vom trimite un link de resetare pe email</p>
                </div>

                <form id="artist-forgot-form" class="space-y-5">
                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" required class="w-full input"
                               placeholder="email@exemplu.ro" autocapitalize="none" autocorrect="off" spellcheck="false">
                    </div>

                    <button type="submit" class="w-full text-white btn bg-primary btn-primary btn-lg">
                        Trimite link de resetare
                    </button>
                </form>

                <p class="mt-6 text-center text-muted">
                    <a href="/artist/login" class="font-medium text-primary">← Înapoi la autentificare</a>
                </p>
            </div>

            <div id="forgot-success" class="hidden p-8 bg-white border rounded-2xl border-border">
                <div class="text-center">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-success/10">
                        <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h2 class="mb-2 text-2xl font-bold text-secondary">Verifică-ți emailul</h2>
                    <p class="mb-2 text-muted">Dacă există un cont cu acest email, am trimis instrucțiunile de resetare la:</p>
                    <p id="sentEmail" class="mb-6 font-semibold text-secondary"></p>

                    <div class="p-4 mb-6 text-left bg-surface rounded-xl">
                        <p class="mb-3 text-sm text-muted">Nu ai primit emailul?</p>
                        <ul class="space-y-2 text-sm text-muted">
                            <li>• Verifică folderul Spam sau Promoții</li>
                            <li>• Linkul expiră în 60 de minute</li>
                            <li>• Poți cere un nou link mai jos</li>
                        </ul>
                    </div>

                    <a href="/artist/login" class="block w-full py-3 text-sm font-medium text-center text-primary">
                        Înapoi la autentificare
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-forgot-password.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
