<?php
/**
 * Login Page
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Autentificare';
$bodyClass = 'min-h-screen flex bg-surface';

// Auth branding customization
$authTitle = 'Bine ai revenit!';
$authSubtitle = 'Accesează contul tău pentru a vedea biletele, a descoperi evenimente noi și a folosi punctele acumulate.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/auth-branding.php';
?>

    <!-- Right Side - Login Form -->
    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <!-- Mobile Logo -->
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
                    <h2 class="text-2xl font-bold text-secondary">Conectează-te</h2>
                    <p class="mt-2 text-muted">Introdu datele tale pentru a accesa contul</p>
                </div>

                <form id="login-form" class="space-y-6">
                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" required
                               class="w-full input"
                               placeholder="email@exemplu.ro">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-secondary">Parola</label>
                            <a href="/parola-uitata" class="text-sm text-primary">Ai uitat parola?</a>
                        </div>
                        <input type="password" id="password" name="password" required
                               class="w-full input"
                               placeholder="••••••••">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember"
                               class="w-4 h-4 rounded text-primary border-border focus:ring-primary">
                        <label for="remember" class="ml-2 text-sm text-muted">Ține-mă minte</label>
                    </div>

                    <button type="submit" class="w-full btn btn-primary btn-lg">
                        Conectează-te
                    </button>
                </form>

                <div class="hidden mt-8">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-border"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-muted">sau continuă cu</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-6">
                        <button type="button" class="flex items-center justify-center gap-2 btn btn-secondary">
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            Google
                        </button>
                        <button type="button" class="flex items-center justify-center gap-2 btn btn-secondary">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            Facebook
                        </button>
                    </div>
                </div>

                <p class="mt-8 text-center text-muted">
                    Nu ai cont?
                    <a href="/inregistrare" class="font-medium text-primary">Creează cont gratuit</a>
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const remember = document.getElementById('remember').checked;

    try {
        const result = await AmbiletAuth.login(email, password, remember);
        if (result.success) {
            AmbiletNotifications.success('Conectare reusita!');
            const redirect = AmbiletUtils.getUrlParam('redirect') || '/user/dashboard';
            setTimeout(() => window.location.href = redirect, 500);
        } else {
            AmbiletNotifications.error(result.message || 'Email sau parola incorecta');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la conectare. Incearca din nou.');
    }
});
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
