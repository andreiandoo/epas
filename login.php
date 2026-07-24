<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Autentificare — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-2 text-center">Contul meu</h1>
        <p class="text-warm-gray text-center mb-8">Autentificare</p>
        <form class="space-y-4 bg-charcoal/40 rounded-2xl p-8" onsubmit="event.preventDefault(); alert('Autentificarea se activează în pasul următor.');">
            <input type="email" placeholder="Email" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <input type="password" placeholder="Parolă" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <button type="submit" class="btn-gold w-full py-3 rounded-lg">Intră în cont</button>
            <div class="flex items-center justify-between text-sm text-warm-gray pt-2">
                <a href="/register.php" class="hover:text-gold">Cont nou</a>
                <a href="/forgot-password.php" class="hover:text-gold">Ai uitat parola?</a>
            </div>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
