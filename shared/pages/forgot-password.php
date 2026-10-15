<?php
$pageTitle = 'Recuperare parolă — ' . SITE_NAME;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-2 text-center">Recuperare parolă</h1>
        <p class="text-muted text-center mb-8">Îți trimitem un link de resetare pe email.</p>
        <form class="space-y-4 bg-surface/40 rounded-2xl p-8" onsubmit="event.preventDefault(); alert('Link de resetare trimis (demo).');">
            <input type="email" placeholder="Email" required class="w-full bg-canvas border border-primary/20 rounded-lg px-4 py-3 focus:border-primary outline-none">
            <button type="submit" class="btn-primary w-full py-3 rounded-lg">Trimite link</button>
            <p class="text-sm text-muted text-center pt-2"><a href="/autentificare" class="text-primary hover:text-primary-light">Înapoi la autentificare</a></p>
        </form>
    </div>
</section>
<?php include ts_layout('footer');
