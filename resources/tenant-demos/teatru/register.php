<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Cont nou — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-8 text-center">Cont nou</h1>
        <form class="space-y-4 bg-charcoal/40 rounded-2xl p-8" onsubmit="event.preventDefault(); alert('Înregistrarea se activează în pasul următor.');">
            <input type="text" placeholder="Nume complet" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <input type="email" placeholder="Email" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <input type="password" placeholder="Parolă" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <button type="submit" class="btn-gold w-full py-3 rounded-lg">Creează cont</button>
            <p class="text-sm text-warm-gray text-center pt-2">Ai deja cont? <a href="/login.php" class="text-gold hover:text-gold-light">Autentifică-te</a></p>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
