<?php
/**
 * Extended Artist — Smart EPK (Modulul 3, stub)
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Smart EPK';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Smart EPK</h1>
            <p class="mt-1 text-muted">Press kit dinamic, share-abil, branduit, cu stats verificate live din platforma.</p>
        </header>

        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl border-2 border-dashed border-border bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/10">
                    <svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">In curand</h2>
                <p class="mx-auto max-w-md text-sm text-muted">Modulul Smart EPK — pagina publica cu URL personalizabil, layout reorderable in 3 template-uri, stats LIVE (evenimente cantate, bilete vandute, orase), variante multiple ("Festival" / "Corporate" / "Club"), generator QR, tracking views — e in dezvoltare.</p>
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
