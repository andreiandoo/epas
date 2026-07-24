<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Contact — ' . SITE_NAME;
$activeNav = 'contact';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="font-display text-5xl lg:text-6xl mb-8">Contact</h1>
        <div class="grid md:grid-cols-2 gap-8">
            <div class="space-y-6">
                <div><p class="text-warm-gray text-sm">Casieria</p><p class="font-display text-lg">Luni–Vineri, 10:00–19:00</p></div>
                <div><p class="text-warm-gray text-sm">Telefon</p><p class="font-display text-lg">+40 21 000 0000</p></div>
                <div><p class="text-warm-gray text-sm">Email</p><p class="font-display text-lg">bilete@teatru.ro</p></div>
                <div><p class="text-warm-gray text-sm">Adresă</p><p class="font-display text-lg">București</p></div>
            </div>
            <form class="space-y-4" onsubmit="event.preventDefault(); this.reset(); alert('Mesaj trimis. Vă mulțumim!');">
                <input type="text" placeholder="Nume" required class="w-full bg-charcoal border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
                <input type="email" placeholder="Email" required class="w-full bg-charcoal border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none">
                <textarea placeholder="Mesaj" rows="5" required class="w-full bg-charcoal border border-gold/20 rounded-lg px-4 py-3 focus:border-gold outline-none"></textarea>
                <button type="submit" class="btn-gold px-8 py-3 rounded-lg">Trimite mesajul</button>
            </form>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
