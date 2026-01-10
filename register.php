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

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/auth-branding.php';
?>

    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-md">
            <div class="lg:hidden text-center mb-8">
                <a href="/" class="inline-flex items-center gap-3">
                    <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-border p-8">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-secondary">Creează cont</h2>
                    <p class="text-muted mt-2">Completează datele pentru a începe</p>
                </div>

                <form id="register-form" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-secondary mb-2">Prenume</label>
                            <input type="text" id="first_name" name="first_name" required class="input w-full" placeholder="Ion">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-secondary mb-2">Nume</label>
                            <input type="text" id="last_name" name="last_name" required class="input w-full" placeholder="Popescu">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-secondary mb-2">Email</label>
                        <input type="email" id="email" name="email" required class="input w-full" placeholder="email@exemplu.ro">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-secondary mb-2">Telefon</label>
                        <input type="tel" id="phone" name="phone" class="input w-full" placeholder="0722 123 456">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-secondary mb-2">Parola</label>
                        <input type="password" id="password" name="password" required minlength="8" class="input w-full" placeholder="Minim 8 caractere">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-secondary mb-2">Confirmă parola</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required class="input w-full" placeholder="Repetă parola">
                    </div>

                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 mt-1 text-primary border-border rounded focus:ring-primary">
                        <label for="terms" class="ml-2 text-sm text-muted">
                            Sunt de acord cu <a href="/terms" class="text-primary">Termenii și condițiile</a>
                            și <a href="/privacy" class="text-primary">Politica de confidențialitate</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-full btn-lg">Creează cont</button>
                </form>

                <p class="text-center text-muted mt-8">
                    Ai deja cont? <a href="/autentificare" class="text-primary font-medium">Conectează-te</a>
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = {
        first_name: document.getElementById('first_name').value,
        last_name: document.getElementById('last_name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        password: document.getElementById('password').value,
        password_confirmation: document.getElementById('password_confirmation').value
    };

    if (formData.password !== formData.password_confirmation) {
        AmbiletNotifications.error('Parolele nu coincid');
        return;
    }

    try {
        const result = await AmbiletAuth.register(formData);
        if (result.success) {
            AmbiletNotifications.success('Cont creat cu succes! Verifică email-ul pentru confirmare.');
            setTimeout(() => window.location.href = '/autentificare', 2000);
        } else {
            AmbiletNotifications.error(result.message || 'Eroare la înregistrare');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la înregistrare. Încearcă din nou.');
    }
});
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
