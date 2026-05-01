<?php
/**
 * Extended Artist — Tour Optimizer (Modulul 4, stub)
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Tour Optimizer';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Tour Optimizer</h1>
            <p class="mt-1 text-muted">Planifica turnee strategic, pe baza datelor reale despre fanii tai si performanta evenimentelor anterioare.</p>
        </header>

        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl border-2 border-dashed border-border bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-rose-500/10">
                    <svg class="h-8 w-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">In curand</h2>
                <p class="mx-auto max-w-md text-sm text-muted">Modulul Tour Optimizer — heatmap densitate fani, recomandari orase, predictii bilete in 3 scenarii de venue, route optimization (TSP), comparator scenarii what-if, export PDF pentru pitch — e in dezvoltare.</p>
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
