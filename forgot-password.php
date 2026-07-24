<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Recuperare parolă — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-2 text-center">Recuperare parolă</h1>
        <p class="text-warm-gray text-center mb-8">Îți trimitem un link de resetare pe email.</p>
        <form class="space-y-4 bg-charcoal/40 rounded-2xl p-8" onsubmit="event.preventDefault(); alert('Link de resetare trimis (demo).');">
            <input type="email" placeholder="Email" required class="w-full bg-midnight border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
            <button type="submit" class="btn-gold w-full py-3 rounded-lg">Trimite link</button>
            <p class="text-sm text-warm-gray text-center pt-2"><a href="/autentificare" class="text-gold hover:text-gold-light">Înapoi la autentificare</a></p>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
