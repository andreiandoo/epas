<?php
/**
 * Artist Account — Dashboard
 * Layout follows resources/marketplaces/ambilet/designs/artist/dashboard.html:
 * dark sidebar (left), mobile header + drawer, then a main column with:
 *   - 4 KPI cards
 *   - 2-column grid (profile completion + next-event card)
 *   - Upcoming events list + recent events
 *
 * Data fetched client-side via /api/marketplace-client/artist/dashboard.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Dashboard';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<?php require __DIR__ . '/_partials/sidebar.php'; ?>

<div class="lg:ml-64 pt-16 lg:pt-0">
    <!-- Desktop page header -->
    <header class="hidden border-b border-border bg-white px-6 py-4 lg:block">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Dashboard</h1>
                <p id="dashboard-greeting" class="text-sm text-muted">Bun venit!</p>
            </div>
            <div class="flex items-center gap-3">
                <a id="public-profile-link" href="#" target="_blank" rel="noopener" class="hidden items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-semibold text-secondary transition-colors hover:bg-surface">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Vezi profilul public
                </a>
                <a href="/artist/cont/detalii" class="btn btn-primary inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-primary-dark hover:shadow-lg">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editează profil
                </a>
            </div>
        </div>
    </header>

    <main class="p-4 lg:p-6">
        <div class="mb-6 lg:hidden">
            <h1 class="text-2xl font-bold text-secondary">Dashboard</h1>
            <p id="dashboard-greeting-mobile" class="text-sm text-muted">Bun venit!</p>
        </div>

        <!-- Unlinked notice (shown when account.artist_id is null) -->
        <div id="unlinked-notice" class="mb-8 hidden rounded-2xl border-2 border-amber-200 bg-amber-50 p-6">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-6 w-6 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-900">Profil nelinkat încă</h3>
                    <p class="mt-1 text-sm text-amber-800">Echipa <?= SITE_NAME ?> nu a asociat încă un profil de artist contului tău. Te vom contacta prin email când e gata.</p>
                </div>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span id="kpi-next-event-label" class="hidden text-xs font-medium text-muted">Următor: —</span>
                </div>
                <p id="kpi-upcoming" class="text-3xl font-bold text-secondary">—</p>
                <p class="text-sm text-muted">Evenimente viitoare</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-success/10">
                        <svg class="h-6 w-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                </div>
                <p id="kpi-total-events" class="text-3xl font-bold text-secondary">—</p>
                <p class="text-sm text-muted">Total evenimente</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent/10">
                        <svg class="h-6 w-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <p id="kpi-followers" class="text-3xl font-bold text-secondary">—</p>
                <p class="text-sm text-muted">Total fani</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-secondary/10">
                        <svg class="h-6 w-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
                <p id="kpi-status" class="text-2xl font-bold text-secondary">—</p>
                <p class="text-sm text-muted">Status cont</p>
            </div>
        </div>

        <!-- Profile completion + Next event -->
        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <!-- Profile completion (replaces the design's "Performance" chart since
                 we don't track per-artist ticket sales yet) -->
            <div id="completion-card" class="rounded-2xl border border-border bg-white p-6 lg:col-span-2">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Completarea profilului</h2>
                        <p class="text-sm text-muted">Cu cât profilul tău este mai complet, cu atât mai vizibil ești pentru organizatori.</p>
                    </div>
                    <span id="completion-percentage" class="text-3xl font-bold text-primary">0%</span>
                </div>
                <div class="mb-4 h-3 overflow-hidden rounded-full bg-surface">
                    <div id="completion-bar" class="h-full rounded-full bg-gradient-to-r from-primary to-accent transition-all duration-500" style="width: 0%"></div>
                </div>
                <div id="completion-fields" class="grid grid-cols-2 gap-2 text-sm md:grid-cols-3"></div>
                <div class="mt-6 border-t border-border pt-4">
                    <a href="/artist/cont/detalii" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        Mergi la editor
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            <!-- Next event card (filled by JS — hidden until we have an upcoming event) -->
            <div id="next-event-card" class="relative hidden overflow-hidden rounded-2xl bg-gradient-to-br from-secondary to-primary-dark p-6 text-white">
                <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-white/5 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-primary/20 blur-2xl"></div>

                <div class="relative z-10">
                    <div class="mb-4 flex items-center gap-2 text-sm text-white/70">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Următorul eveniment</span>
                    </div>

                    <div class="mb-4 flex items-start gap-4">
                        <div class="flex-shrink-0 rounded-xl bg-white/10 px-3 py-2 text-center backdrop-blur">
                            <p id="next-event-day" class="text-2xl font-extrabold leading-none">—</p>
                            <p id="next-event-month" class="mt-1 text-xs uppercase tracking-wider">—</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 id="next-event-title" class="mb-1 truncate text-lg font-bold leading-tight">—</h3>
                            <p class="flex items-center gap-1 truncate text-sm text-white/70">
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                <span id="next-event-time" class="truncate">—</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- "No upcoming event" placeholder (shown when next-event-card is hidden) -->
            <div id="no-next-event" class="rounded-2xl border-2 border-dashed border-border bg-surface p-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white">
                    <svg class="h-6 w-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="font-semibold text-secondary">Niciun eveniment viitor</p>
                <p class="mt-1 text-sm text-muted">Organizatorii te vor adăuga la evenimentele lor.</p>
            </div>
        </div>

        <!-- Upcoming events list (nearest-future first) + Account info sidecard -->
        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-border bg-white p-6 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Evenimente viitoare</h2>
                    <a href="/artist/cont/evenimente" class="text-sm font-medium text-primary hover:underline">Vezi toate →</a>
                </div>
                <div id="upcoming-events-list" class="space-y-3">
                    <p class="py-8 text-center text-sm text-muted">Se încarcă…</p>
                </div>
            </div>

            <!-- Account info card -->
            <div class="rounded-2xl border border-border bg-white p-6">
                <h2 class="mb-4 text-lg font-bold text-secondary">Informații cont</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-border pb-3">
                        <dt class="text-muted">Email</dt>
                        <dd id="account-email" class="text-right font-medium text-secondary">—</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 border-b border-border pb-3">
                        <dt class="text-muted">Email verificat</dt>
                        <dd id="account-email-verified" class="text-right font-medium text-secondary">—</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 border-b border-border pb-3">
                        <dt class="text-muted">Ultim login</dt>
                        <dd id="account-last-login" class="text-right font-medium text-secondary">—</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Profil linkat</dt>
                        <dd id="account-linked-artist" class="text-right font-medium text-secondary">—</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Past events list (most-recently-ended first) -->
        <div class="grid gap-6">
            <div class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Evenimente recente</h2>
                    <a href="/artist/cont/evenimente?filter=past" class="text-sm font-medium text-primary hover:underline">Vezi toate →</a>
                </div>
                <div id="past-events-list" class="space-y-3">
                    <p class="py-8 text-center text-sm text-muted">Se încarcă…</p>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-dashboard.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
