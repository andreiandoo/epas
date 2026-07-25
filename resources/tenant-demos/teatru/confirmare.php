<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$status  = $_GET['status'] ?? '';
$ok      = $status === 'paid' && $orderId > 0;

$pageTitle = ($ok ? 'Comandă confirmată' : 'Comandă anulată') . ' — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-40 pb-32 px-4 text-center" x-data x-init="localStorage.removeItem('teatru_cart')">
    <div class="max-w-lg mx-auto">
        <?php if ($ok): ?>
            <div class="w-20 h-20 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="font-display text-4xl mb-3">Comandă confirmată!</h1>
            <p class="text-ivory/70 mb-2">Îți mulțumim. Plata a fost procesată (demo).</p>
            <p class="text-warm-gray text-sm mb-8">Comanda <span class="text-gold font-mono">#<?= e((string) $orderId) ?></span> — biletele au fost emise și trimise pe email.</p>
            <a href="/" class="btn-gold px-8 py-4 rounded-lg inline-block">Înapoi acasă</a>
        <?php else: ?>
            <div class="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <h1 class="font-display text-4xl mb-3">Plată neefectuată</h1>
            <p class="text-ivory/70 mb-8">Comanda nu a fost finalizată. Poți încerca din nou oricând.</p>
            <a href="/repertoriu" class="btn-gold px-8 py-4 rounded-lg inline-block">Vezi spectacolele</a>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
