<?php
/**
 * Artist Account — Login
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Cont artist — autentificare';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Bine ai revenit, artist!';
$authSubtitle = 'Conectează-te pentru a-ți gestiona profilul, evenimentele și informațiile publice.';

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
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

            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-secondary">Cont artist</h2>
                    <p class="mt-2 text-muted">Introdu datele de autentificare</p>
                </div>

                <!-- Status banner (filled by JS when login fails with structured code) -->
                <div id="login-status" class="hidden p-3 mb-5 text-sm rounded-lg"></div>

                <form id="artist-login-form" class="space-y-6">
                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" required
                               class="w-full input"
                               placeholder="email@exemplu.ro"
                               autocomplete="email"
                               autocapitalize="none"
                               autocorrect="off"
                               spellcheck="false"
                               inputmode="email">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-secondary">Parola</label>
                            <a href="/artist/parola-uitata" class="text-sm text-primary">Ai uitat parola?</a>
                        </div>
                        <input type="password" id="password" name="password" required
                               class="w-full input"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               autocapitalize="none"
                               autocorrect="off"
                               spellcheck="false">
                    </div>

                    <button type="submit" class="w-full text-white btn bg-primary btn-primary btn-lg">
                        Conectează-te
                    </button>
                </form>

                <p class="mt-8 text-center text-muted">
                    Nu ai cont de artist?
                    <a href="/artist/inregistrare" class="font-medium text-primary">Creează cont gratuit</a>
                </p>
            </div>

            <a href="/" class="block mt-6 text-center text-sm text-muted hover:text-primary">← Înapoi la <?= SITE_NAME ?></a>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-login.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
