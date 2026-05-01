<?php
/**
 * Extended Artist — Booking Marketplace (Modulul 2, stub)
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Booking Marketplace';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Booking Marketplace</h1>
            <p class="mt-1 text-muted">Primesti cereri de booking de la organizatori, venue-uri si agentii. Negocieri, contracte automate, calendar.</p>
        </header>

        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl border-2 border-dashed border-border bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10">
                    <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">In curand</h2>
                <p class="mx-auto max-w-md text-sm text-muted">Modulul Booking Marketplace — listing public cu disponibilitate, cereri de booking, negocieri pe pasi, contracte bilingv generate automat, reviewuri post-eveniment — e in dezvoltare.</p>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) { window.location.href = '/artist/login'; return; }
    fetch('/api/proxy.php?action=artist.extended-artist.status', { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token } })
        .then(r => r.json())
        .then(payload => { if (payload?.data?.enabled !== true) window.location.href = '/artist/cont/extended-artist'; })
        .catch(() => { window.location.href = '/artist/cont/extended-artist'; });
})();
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
