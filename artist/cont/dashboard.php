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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                </div>
                <p id="kpi-local-fans" class="text-3xl font-bold text-secondary">—</p>
                <p class="text-sm text-muted">Fani pe <?= SITE_NAME ?></p>
                <p class="mt-1 text-xs text-muted">Persoane care te-au adăugat la favorite.</p>
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

        <!-- Social stats — one card per platform. Filled by JS. The whole
             block is hidden when there are no trackable IDs / synced
             stats yet (decided by JS based on the social_stats payload). -->
        <div id="social-stats-section" class="mb-8 hidden">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-secondary">Statistici sociale</h2>
                    <p id="social-stats-updated" class="text-sm text-muted">Sincronizate la ultima rulare a refresh-ului.</p>
                </div>
                <a href="/artist/cont/detalii#tab-social" class="text-sm font-medium text-primary hover:underline">Configurare →</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <!-- Spotify -->
                <div data-social-card="spotify" class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center gap-3 border-b border-border bg-[#1DB954]/5 px-4 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#1DB954]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.56.3z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-secondary">Spotify</span>
                    </div>
                    <div class="p-4">
                        <p data-social-followers class="text-2xl font-bold text-secondary">—</p>
                        <p class="text-xs text-muted">followers</p>
                        <div data-social-secondary class="mt-3 hidden border-t border-border pt-2">
                            <p data-social-secondary-value class="text-sm font-semibold text-secondary">—</p>
                            <p data-social-secondary-label class="text-[10px] uppercase tracking-wider text-muted">listeners lunari</p>
                        </div>
                        <div data-social-empty class="mt-2 hidden text-xs text-muted">Adaugă Spotify Artist ID pentru a sincroniza.</div>
                    </div>
                </div>

                <!-- YouTube -->
                <div data-social-card="youtube" class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center gap-3 border-b border-border bg-[#FF0000]/5 px-4 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#FF0000]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-secondary">YouTube</span>
                    </div>
                    <div class="p-4">
                        <p data-social-followers class="text-2xl font-bold text-secondary">—</p>
                        <p class="text-xs text-muted">subscribers</p>
                        <div data-social-secondary class="mt-3 hidden border-t border-border pt-2">
                            <p data-social-secondary-value class="text-sm font-semibold text-secondary">—</p>
                            <p data-social-secondary-label class="text-[10px] uppercase tracking-wider text-muted">vizualizări totale</p>
                        </div>
                        <div data-social-empty class="mt-2 hidden text-xs text-muted">Adaugă YouTube Channel ID pentru a sincroniza.</div>
                    </div>
                </div>

                <!-- Facebook -->
                <div data-social-card="facebook" class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center gap-3 border-b border-border bg-[#1877F2]/5 px-4 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#1877F2]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-secondary">Facebook</span>
                    </div>
                    <div class="p-4">
                        <p data-social-followers class="text-2xl font-bold text-secondary">—</p>
                        <p class="text-xs text-muted">followers</p>
                        <div data-social-empty class="mt-2 hidden text-xs text-muted">Adaugă link Facebook pentru a sincroniza.</div>
                    </div>
                </div>

                <!-- Instagram -->
                <div data-social-card="instagram" class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center gap-3 border-b border-border bg-gradient-to-r from-[#FFDC80]/10 via-[#E1306C]/10 to-[#5B51DB]/10 px-4 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-[#FFDC80] via-[#E1306C] to-[#5B51DB]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-secondary">Instagram</span>
                    </div>
                    <div class="p-4">
                        <p data-social-followers class="text-2xl font-bold text-secondary">—</p>
                        <p class="text-xs text-muted">followers</p>
                        <div data-social-empty class="mt-2 hidden text-xs text-muted">Adaugă link Instagram pentru a sincroniza.</div>
                    </div>
                </div>

                <!-- TikTok -->
                <div data-social-card="tiktok" class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center gap-3 border-b border-border bg-black/5 px-4 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-black">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-secondary">TikTok</span>
                    </div>
                    <div class="p-4">
                        <p data-social-followers class="text-2xl font-bold text-secondary">—</p>
                        <p class="text-xs text-muted">followers</p>
                        <div data-social-empty class="mt-2 hidden text-xs text-muted">Adaugă link TikTok pentru a sincroniza.</div>
                    </div>
                </div>
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
