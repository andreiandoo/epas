<?php
/**
 * Register Page
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Creeaza cont';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Alătură-te comunității!';
$authSubtitle = 'Creează un cont gratuit pentru a cumpăra bilete, a acumula puncte și a primi oferte exclusive.';
$authFeatures = [
    ['icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', 'text' => 'Bilete digitale instantanee'],
    ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => '1 punct pentru fiecare 10 lei'],
    ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Tranzacții 100% sigure'],
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

            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-secondary">Creează cont</h2>
                    <p class="mt-2 text-muted">Completează datele pentru a începe</p>
                </div>

                <form id="register-form" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block mb-2 text-sm font-medium text-secondary">Prenume</label>
                            <input type="text" id="first_name" name="first_name" required class="w-full input" placeholder="Ion">
                        </div>
                        <div>
                            <label for="last_name" class="block mb-2 text-sm font-medium text-secondary">Nume</label>
                            <input type="text" id="last_name" name="last_name" required class="w-full input" placeholder="Popescu">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" required class="w-full input" placeholder="email@exemplu.ro">
                    </div>

                    <div>
                        <label for="phone" class="block mb-2 text-sm font-medium text-secondary">Telefon</label>
                        <input type="tel" id="phone" name="phone" class="w-full input" placeholder="0722 123 456">
                    </div>

                    <div>
                        <label for="password" class="block mb-2 text-sm font-medium text-secondary">Parola</label>
                        <input type="password" id="password" name="password" required minlength="8" class="w-full input" placeholder="Minim 8 caractere">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block mb-2 text-sm font-medium text-secondary">Confirmă parola</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required class="w-full input" placeholder="Repetă parola">
                    </div>

                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 mt-1 rounded text-primary border-border focus:ring-primary">
                        <label for="terms" class="ml-2 text-sm text-muted">
                            Sunt de acord cu <a href="/terms" class="text-primary">Termenii și condițiile</a>
                            și <a href="/privacy" class="text-primary">Politica de confidențialitate</a>
                        </label>
                    </div>

                    <button type="submit" class="w-full btn btn-primary bg-primary btn-lg" aria-label="Creează cont">Creează cont</button>
                </form>

                <p class="mt-8 text-center text-muted">
                    Ai deja cont? <a href="/autentificare" class="font-medium text-primary">Conectează-te</a>
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/register.js') . '"></script>';

require_once __DIR__ . '/includes/scripts.php';
?>
