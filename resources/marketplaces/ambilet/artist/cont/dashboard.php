<?php
/**
 * Artist Account — Dashboard
 * KPI cards + profile completion bar + recent events. All data fetched
 * client-side from /api/marketplace-client/artist/dashboard so the page
 * is identical for crawlers (logged-out → redirected to login by JS).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Dashboard';
$bodyClass = 'min-h-screen bg-surface';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<div class="flex min-h-screen">
    <?php require __DIR__ . '/_partials/sidebar.php'; ?>
    <div class="flex flex-col flex-1 min-w-0">
        <?php require __DIR__ . '/_partials/header.php'; ?>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-start justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-secondary">Dashboard</h1>
                        <p id="dashboard-greeting" class="mt-1 text-muted">Bun venit!</p>
                    </div>
                </div>

                <!-- Profile completion banner — hidden by default; shown only when we have a linked profile -->
                <div id="completion-banner" class="hidden p-6 mb-8 bg-white border rounded-2xl border-border">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-secondary">Completarea profilului</h2>
                            <p class="mt-1 text-sm text-muted">Completează detaliile pentru ca profilul tău să apară mai bine în căutări.</p>
                        </div>
                        <span id="completion-percentage" class="text-2xl font-bold text-primary">0%</span>
                    </div>
                    <div class="w-full h-2 mb-4 overflow-hidden rounded-full bg-surface">
                        <div id="completion-bar" class="h-full transition-all bg-primary" style="width: 0%"></div>
                    </div>
                    <a href="/artist/cont/detalii" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        Mergi la editor →
                    </a>
                </div>

                <!-- Unlinked profile notice — shows when account has no artist_id yet -->
                <div id="unlinked-notice" class="hidden p-6 mb-8 border-2 border-amber-200 rounded-2xl bg-amber-50">
                    <div class="flex items-start gap-3">
                        <svg class="flex-shrink-0 w-6 h-6 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-amber-900">Profil nelinkat încă</h3>
                            <p class="mt-1 text-sm text-amber-800">Echipa <?= SITE_NAME ?> nu a asociat încă un profil de artist contului tău. Te vom contacta prin email când e gata.</p>
                        </div>
                    </div>
                </div>

                <!-- KPI cards -->
                <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2 lg:grid-cols-4">
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center gap-3 mb-2 text-sm text-muted">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Evenimente viitoare
                        </div>
                        <p id="kpi-upcoming" class="text-3xl font-bold text-secondary">—</p>
                    </div>
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center gap-3 mb-2 text-sm text-muted">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Total evenimente
                        </div>
                        <p id="kpi-total-events" class="text-3xl font-bold text-secondary">—</p>
                    </div>
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center gap-3 mb-2 text-sm text-muted">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Total fani
                        </div>
                        <p id="kpi-followers" class="text-3xl font-bold text-secondary">—</p>
                    </div>
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center gap-3 mb-2 text-sm text-muted">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Status
                        </div>
                        <p id="kpi-status" class="text-2xl font-bold text-secondary">—</p>
                    </div>
                </div>

                <!-- Recent events list -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-secondary">Evenimente recente</h2>
                        <a href="/artist/cont/evenimente" class="text-sm font-medium text-primary hover:underline">Vezi toate →</a>
                    </div>
                    <div id="recent-events-list" class="space-y-3">
                        <p class="py-8 text-center text-sm text-muted">Se încarcă…</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-dashboard.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
