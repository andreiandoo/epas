<?php
/**
 * Artist Account — Reset Password
 * Token + email come from the URL query string (link emailed by Laravel).
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Cont artist — resetare parolă';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Setează parola nouă';
$authSubtitle = 'Aproape gata. Alege o parolă nouă pentru contul tău de artist.';

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-secondary">Parolă nouă</h2>
                    <p class="mt-2 text-muted">Alege o parolă de cel puțin 8 caractere</p>
                </div>

                <div id="reset-error" class="hidden p-3 mb-5 text-sm text-center text-red-700 border border-red-200 rounded-lg bg-red-50"></div>
                <div id="reset-success" class="hidden p-3 mb-5 text-sm text-center text-green-700 border border-green-200 rounded-lg bg-green-50"></div>

                <form id="artist-reset-form" class="space-y-5">
                    <div>
                        <label for="password" class="block mb-2 text-sm font-medium text-secondary">Parolă nouă</label>
                        <input type="password" id="password" name="password" required minlength="8"
                               class="w-full input" placeholder="Minim 8 caractere" autocomplete="new-password">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block mb-2 text-sm font-medium text-secondary">Confirmă parola</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                               class="w-full input" placeholder="Repetă parola" autocomplete="new-password">
                    </div>

                    <button type="submit" class="w-full text-white btn bg-primary btn-primary btn-lg">
                        Resetează parola
                    </button>
                </form>

                <p class="mt-6 text-center text-muted">
                    <a href="/artist/login" class="font-medium text-primary">← Înapoi la autentificare</a>
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-reset-password.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
