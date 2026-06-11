<?php
/**
 * Artist Account — Events list
 * Layout follows resources/marketplaces/ambilet/designs/artist/events.html.
 * Stats summary + filter bar (search, city, sort, status pills) + card list
 * with date badge, status badges (LIVE / Încheiat), bilete progress bar
 * (when ticket data is available) and a "vezi pagina eveniment" CTA.
 *
 * Backend: GET /api/marketplace-client/artist/events?filter=upcoming|past|all.
 * Search/city/sort happen client-side over the full fetched set.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Evenimente';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<?php require __DIR__ . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Evenimentele mele</h1>
            <p class="mt-1 text-muted">Toate evenimentele în care ești prezent ca artist.</p>
        </div>

        <!-- Stats summary -->
        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-white p-5">
                <div class="mb-2 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-muted">Total</span>
                </div>
                <p id="stat-total" class="text-2xl font-bold text-secondary">—</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-5">
                <div class="mb-2 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-success/10">
                        <svg class="h-5 w-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-muted">Viitoare</span>
                </div>
                <p id="stat-upcoming" class="text-2xl font-bold text-secondary">—</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-5">
                <div class="mb-2 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent/10">
                        <svg class="h-5 w-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-muted">Trecute</span>
                </div>
                <p id="stat-past" class="text-2xl font-bold text-secondary">—</p>
            </div>

            <div class="rounded-2xl border border-border bg-white p-5">
                <div class="mb-2 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary/10">
                        <svg class="h-5 w-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-muted">Orașe</span>
                </div>
                <p id="stat-cities" class="text-2xl font-bold text-secondary">—</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="mb-6 rounded-2xl border border-border bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row">
                <!-- Status pills -->
                <div class="inline-flex self-start rounded-xl border border-border bg-surface p-1">
                    <button type="button" data-filter-status="upcoming" class="filter-status rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
                        Viitoare
                        <span class="filter-count ml-1.5 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">0</span>
                    </button>
                    <button type="button" data-filter-status="past" class="filter-status rounded-lg px-4 py-2 text-sm font-medium text-muted transition-colors hover:text-secondary">
                        Trecute
                        <span class="filter-count ml-1.5 rounded-full bg-border px-1.5 py-0.5 text-xs">0</span>
                    </button>
                    <button type="button" data-filter-status="all" class="filter-status rounded-lg px-4 py-2 text-sm font-medium text-muted transition-colors hover:text-secondary">
                        Toate
                    </button>
                </div>

                <!-- Search -->
                <div class="relative min-w-[200px] flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="filter-search" placeholder="Caută eveniment, locație, oraș…" class="input pl-10">
                </div>

                <!-- City filter — pr-10 reserves room for the SVG arrow
                     drawn via partials/_forms.css `select` rule. Without
                     it, the .input padding (1rem) overrides and the arrow
                     overlaps the option text. -->
                <select id="filter-city" class="input w-full pr-10 lg:w-auto">
                    <option value="">Toate orașele</option>
                </select>

                <!-- Sort -->
                <select id="filter-sort" class="input w-full pr-10 lg:w-auto">
                    <option value="date_asc">Cele mai apropiate</option>
                    <option value="date_desc">Cele mai îndepărtate</option>
                </select>
            </div>
        </div>

        <!-- Events list -->
        <div id="events-list" class="space-y-4">
            <div class="rounded-2xl border border-border bg-white p-12 text-center">
                <p class="text-muted">Se încarcă evenimentele…</p>
            </div>
        </div>

        <!-- Empty: filters returned nothing -->
        <div id="empty-filtered" class="hidden rounded-2xl border border-border bg-white py-16 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted/10">
                <svg class="h-8 w-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <p class="mb-1 font-semibold text-secondary">Niciun rezultat</p>
            <p class="mb-4 text-sm text-muted">Nu există evenimente care să corespundă filtrelor active.</p>
            <button type="button" id="reset-filters-btn" class="btn btn-secondary btn-sm inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 py-2 text-sm font-semibold text-secondary transition-colors hover:bg-surface">Resetează filtrele</button>
        </div>

        <!-- Empty: no events at all -->
        <div id="empty-all" class="hidden rounded-2xl border border-border bg-white py-16 text-center">
            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-primary/5">
                <svg class="h-12 w-12 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="mb-2 text-xl font-bold text-secondary">Niciun eveniment încă</h2>
            <p class="mx-auto mb-6 max-w-md text-muted">Organizatorii te vor adăuga la evenimentele lor. Asigură-te că profilul tău este complet și vizibil pentru a fi descoperit mai ușor.</p>
            <a href="/artist/cont/detalii" class="btn btn-primary inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-md transition-all hover:bg-primary-dark hover:shadow-lg">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Completează profilul
            </a>
        </div>
    </div>
</main>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-evenimente.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
