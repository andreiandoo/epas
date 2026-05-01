<?php
/**
 * Extended Artist — Fan CRM (Modulul 1, stub)
 *
 * Pagina-placeholder. Iconul + descrierea reflecta modulul, dar UI-ul
 * complet (heatmap, segmente, lista fani) vine in Faza 2.
 *
 * Gating: server-side nu blocheaza accesul (pagina e statica). Frontend-ul
 * verifica /api/proxy.php?action=artist.extended-artist.status si redirecteaza
 * catre /artist/cont/extended-artist daca artistul nu are acces.
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Fan CRM';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Fan CRM</h1>
            <p class="mt-1 text-muted">Analiza profunda a publicului tau — cine sunt fanii tai, unde locuiesc, ce cumpara.</p>
        </header>

        <div data-extended-artist-gate class="mx-auto max-w-3xl">
            <div class="rounded-2xl border-2 border-dashed border-border bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                    <svg class="h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">In curand</h2>
                <p class="mx-auto max-w-md text-sm text-muted">Modulul Fan CRM — harta heatmap geografica, segmente VIP/Loial/Dormit/Nou, cohorte de retentie, top fani — e in dezvoltare. Vom anunta lansarea pe email.</p>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) {
        window.location.href = '/artist/login';
        return;
    }

    fetch('/api/proxy.php?action=artist.extended-artist.status', {
        headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
    })
        .then(r => r.json())
        .then(payload => {
            if (payload?.data?.enabled !== true) {
                window.location.href = '/artist/cont/extended-artist';
            }
        })
        .catch(() => {
            window.location.href = '/artist/cont/extended-artist';
        });
})();
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
