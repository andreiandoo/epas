<?php
/**
 * bilete.online — Organizator › Servicii Extra (v3).
 * Route: /organizator/servicii
 *
 * Promotion / email-marketing / ad-tracking / campaign services. 3-step modal
 * wizard (select activity → configure → pay), audience builder with searchable
 * multiselects + partial-match pricing, email preview templates, and placement
 * preview mockups. Ported 1:1 from ambilet to v3 + shell. Activity-centric copy
 * ("eveniment" → "activitate"). All JS logic / IDs / field names preserved.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Servicii Extra';
$currentPage = 'services';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Success Banner -->
        <div id="success-banner" class="mb-6 hidden rounded-2xl border-2 border-forest bg-forest/10 p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-forest text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                <div class="flex-1">
                    <p class="font-bold text-forest" id="success-title">Succes!</p>
                    <p class="text-sm text-forest/80" id="success-message">Operațiunea a fost finalizată cu succes.</p>
                </div>
                <button onclick="closeSuccessBanner()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full text-forest transition hover:bg-forest/10"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
        </div>

        <!-- Cancelled Banner -->
        <div id="cancelled-banner" class="mb-6 hidden rounded-2xl border-2 border-ochre bg-ochre/10 p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-ochre text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></span>
                <div class="flex-1">
                    <p class="font-bold text-ochre">Plată anulată</p>
                    <p class="text-sm text-ink-soft">Plata a fost anulată. Poți încerca din nou oricând dorești.</p>
                </div>
                <button onclick="closeCancelledBanner()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full text-ochre transition hover:bg-ochre/10"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
        </div>

        <!-- Page Header -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Servicii Extra</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Promovează-ți activitățile și crește vânzările.</p>
            </div>
            <a href="/organizator/servicii/comenzi" class="inline-flex items-center gap-2 self-start rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper sm:self-auto">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Comenzile mele
            </a>
        </div>

        <!-- Services Grid -->
        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Activity Featuring -->
            <div class="flex flex-col justify-between overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-vermilion text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></span>
                        <div class="flex-1">
                            <h3 class="mb-1 font-display text-lg font-bold">Promovare Activitate</h3>
                            <p class="mb-4 text-sm text-ink-soft">Afișează activitatea ta pe prima pagină, în secțiunea de recomandări, pe pagina categoriei sau a orașului activității.</p>
                            <div class="mb-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-vermilion/10 px-2 py-1 text-xs font-bold text-vermilion">Hero Prima Pagină</span>
                                <span class="rounded-full bg-vermilion/10 px-2 py-1 text-xs font-bold text-vermilion">Recomandări</span>
                                <span class="rounded-full bg-vermilion/10 px-2 py-1 text-xs font-bold text-vermilion">Categorie</span>
                                <span class="rounded-full bg-vermilion/10 px-2 py-1 text-xs font-bold text-vermilion">Oraș</span>
                            </div>
                            <p class="text-sm font-bold text-ink">De la <span class="text-vermilion" id="card-featuring-price">40 RON</span> / zi</p>
                        </div>
                    </div>
                </div>
                <div class="border-t-2 border-ink/10 bg-paper-2 px-6 py-4">
                    <button onclick="openServiceModal('featuring')" class="flex w-full items-center justify-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Cumpără Promovare
                    </button>
                </div>
            </div>

            <!-- Email Marketing (hidden) -->
            <div class="hidden flex-col justify-between overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-ochre text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
                        <div class="flex-1">
                            <h3 class="mb-1 font-display text-lg font-bold">Email Marketing</h3>
                            <p class="mb-4 text-sm text-ink-soft">Trimite emailuri targetate către baza noastră de utilizatori sau doar către clienții tăi anteriori.</p>
                            <div class="mb-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-ochre/10 px-2 py-1 text-xs font-bold text-ochre">Baza Completă</span>
                                <span class="rounded-full bg-ochre/10 px-2 py-1 text-xs font-bold text-ochre">Audiență Filtrată</span>
                                <span class="rounded-full bg-ochre/10 px-2 py-1 text-xs font-bold text-ochre">Clienții Tăi</span>
                            </div>
                            <p class="text-sm font-bold text-ink">De la <span class="text-ochre" id="card-email-price">0.40 RON</span> / email</p>
                        </div>
                    </div>
                </div>
                <div class="border-t-2 border-ink/10 bg-paper-2 px-6 py-4">
                    <button onclick="openServiceModal('email')" class="flex w-full items-center justify-center gap-2 rounded-full bg-ochre px-5 py-2.5 text-sm font-bold text-paper transition hover:opacity-90">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Cumpără Campanie Email
                    </button>
                </div>
            </div>

            <!-- Ad Tracking -->
            <div class="flex flex-col justify-between overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-sky text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>
                        <div class="flex-1">
                            <h3 class="mb-1 font-display text-lg font-bold">Tracking Campanii Ads</h3>
                            <p class="mb-4 text-sm text-ink-soft">Conectează campaniile tale Facebook, Google sau TikTok pentru a urmări conversiile și ROI-ul.</p>
                            <div class="mb-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">Facebook Ads</span>
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">Google Ads</span>
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">TikTok Ads</span>
                            </div>
                            <p class="text-sm font-bold text-ink">De la <span class="text-sky">99 RON</span> / lună</p>
                        </div>
                    </div>
                </div>
                <div class="border-t-2 border-ink/10 bg-paper-2 px-6 py-4">
                    <button onclick="openServiceModal('tracking')" class="flex w-full items-center justify-center gap-2 rounded-full bg-sky px-5 py-2.5 text-sm font-bold text-paper transition hover:opacity-90">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Activează Tracking
                    </button>
                </div>
            </div>

            <!-- Ad Campaign Creation (hidden) -->
            <div class="hidden flex-col justify-between overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-sky text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg></span>
                        <div class="flex-1">
                            <h3 class="mb-1 font-display text-lg font-bold">Creare Campanii Ads</h3>
                            <p class="mb-4 text-sm text-ink-soft">Lasă echipa noastră să creeze și să gestioneze campanii publicitare profesionale pentru activitatea ta.</p>
                            <div class="mb-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">Strategie Completă</span>
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">Design Creativ</span>
                                <span class="rounded-full bg-sky/10 px-2 py-1 text-xs font-bold text-sky">Management</span>
                            </div>
                            <p class="text-sm font-bold text-ink">De la <span class="text-sky">499 RON</span> / campanie</p>
                        </div>
                    </div>
                </div>
                <div class="border-t-2 border-ink/10 bg-paper-2 px-6 py-4">
                    <button onclick="openServiceModal('campaign')" class="flex w-full items-center justify-center gap-2 rounded-full bg-sky px-5 py-2.5 text-sm font-bold text-paper transition hover:opacity-90">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Solicită Campanie
                    </button>
                </div>
            </div>
        </div>

        <!-- Active Services -->
        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="flex items-center justify-between border-b-2 border-ink/10 p-6">
                <h2 class="font-display text-lg font-bold">Servicii Active</h2>
                <select id="service-filter" class="rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
                    <option value="">Toate</option>
                    <option value="featuring">Promovare</option>
                    <option value="email">Email Marketing</option>
                    <option value="tracking">Ad Tracking</option>
                    <option value="campaign">Campanii Ads</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Serviciu</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Activitate</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Detalii</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Perioadă</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-bold text-ink">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="services-list" class="divide-y divide-ink/10"></tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Service Modal -->
<div id="service-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b-2 border-ink/10 bg-paper p-6">
            <h3 id="modal-title" class="font-display text-xl font-bold">Configurează Serviciu</h3>
            <button onclick="closeServiceModal()" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">&times;</button>
        </div>

        <!-- Step Indicator -->
        <div class="border-b-2 border-ink/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2" id="step-1-indicator">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-vermilion text-sm font-bold text-paper">1</div>
                    <span class="text-sm font-bold text-ink">Selectează Activitate</span>
                </div>
                <div class="mx-4 h-0.5 flex-1 bg-ink/10"></div>
                <div class="flex items-center gap-2" id="step-2-indicator">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-ink/15 text-sm font-bold text-ink-soft">2</div>
                    <span class="text-sm font-medium text-ink-soft">Configurează</span>
                </div>
                <div class="mx-4 h-0.5 flex-1 bg-ink/10"></div>
                <div class="flex items-center gap-2" id="step-3-indicator">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-ink/15 text-sm font-bold text-ink-soft">3</div>
                    <span class="text-sm font-medium text-ink-soft">Plată</span>
                </div>
            </div>
        </div>

        <form id="service-form" class="p-6" novalidate>
            <input type="hidden" id="service-type">

            <!-- Step 1: Select Activity -->
            <div id="step-1" class="step-content">
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Selectează Activitatea *</label>
                <select id="service-event" class="mb-4 w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                    <option value="">Alege o activitate...</option>
                </select>
                <div id="event-preview" class="mb-4 hidden rounded-xl bg-paper-2 p-4">
                    <div class="flex gap-4">
                        <img id="event-image" src="" alt="" class="h-20 w-20 rounded-lg object-cover">
                        <div>
                            <h4 id="event-title" class="font-bold text-ink"></h4>
                            <p id="event-date" class="text-sm text-ink-soft"></p>
                            <p id="event-venue" class="text-sm text-ink-soft"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Configuration -->
            <div id="step-2" class="step-content hidden">
                <!-- Featuring Options -->
                <div id="featuring-options" class="hidden space-y-4">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Unde vrei să apară activitatea?</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label class="block cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="home_hero" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-4 pr-9 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                    <p class="font-bold text-ink">Prima pagină - Hero</p>
                                    <p class="text-sm text-ink-soft">Vizibilitate maximă, banner principal</p>
                                    <p class="mt-2 text-sm font-bold text-vermilion" data-price-key="home_hero">— RON / zi</p>
                                </div>
                            </label>
                            <button type="button" onclick="showPlacementPreview('home_hero')" class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-lg border-2 border-ink/15 bg-paper text-ink-soft transition hover:border-ink hover:text-vermilion" title="Previzualizează plasamentul"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                        </div>
                        <div class="relative">
                            <label class="block cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="home_recommendations" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-4 pr-9 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                    <p class="font-bold text-ink">Prima pagină - Recomandări</p>
                                    <p class="text-sm text-ink-soft">Secțiunea de recomandări</p>
                                    <p class="mt-2 text-sm font-bold text-vermilion" data-price-key="home_recommendations">— RON / zi</p>
                                </div>
                            </label>
                            <button type="button" onclick="showPlacementPreview('home_recommendations')" class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-lg border-2 border-ink/15 bg-paper text-ink-soft transition hover:border-ink hover:text-vermilion" title="Previzualizează plasamentul"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                        </div>
                        <div class="relative">
                            <label class="block cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="category" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-4 pr-9 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                    <p class="font-bold text-ink">Pagina categorie activitate</p>
                                    <p class="text-sm text-ink-soft">Audiență targetată pe categorie</p>
                                    <p class="mt-2 text-sm font-bold text-vermilion" data-price-key="category">— RON / zi</p>
                                </div>
                            </label>
                            <button type="button" onclick="showPlacementPreview('category')" class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-lg border-2 border-ink/15 bg-paper text-ink-soft transition hover:border-ink hover:text-vermilion" title="Previzualizează plasamentul"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                        </div>
                        <div class="relative">
                            <label class="block cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="city" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-4 pr-9 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                    <p class="font-bold text-ink">Pagina oraș activitate</p>
                                    <p class="text-sm text-ink-soft">Audiență locală din orașul tău</p>
                                    <p class="mt-2 text-sm font-bold text-vermilion" data-price-key="city">— RON / zi</p>
                                </div>
                            </label>
                            <button type="button" onclick="showPlacementPreview('city')" class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-lg border-2 border-ink/15 bg-paper text-ink-soft transition hover:border-ink hover:text-vermilion" title="Previzualizează plasamentul"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Data Început *</label>
                            <input type="date" id="featuring-start" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Data Sfârșit *</label>
                            <input type="date" id="featuring-end" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                        </div>
                    </div>
                </div>

                <!-- Email Marketing Options -->
                <div id="email-options" class="hidden space-y-4">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Selectează Audiența</label>
                    <div class="space-y-3">
                        <label class="block cursor-pointer">
                            <input type="radio" name="email_audience" value="own" class="peer sr-only" checked>
                            <div class="rounded-xl border-2 border-ink/15 p-4 peer-checked:border-ochre peer-checked:bg-ochre/5">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="font-bold text-ink">Clienții Tăi</p>
                                        <p class="text-sm text-ink-soft">Participanții de la activitățile tale anterioare</p>
                                        <p class="mt-1 text-xs font-bold text-ochre">0.40 RON / email</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-ochre" id="audience-own-count">0</p>
                                        <p class="text-xs text-ink-soft">clienți</p>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <label class="block cursor-pointer">
                            <input type="radio" name="email_audience" value="marketplace" class="peer sr-only">
                            <div class="rounded-xl border-2 border-ink/15 p-4 peer-checked:border-ochre peer-checked:bg-ochre/5">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="font-bold text-ink">Baza de Date Marketplace</p>
                                        <p class="text-sm text-ink-soft">Toți utilizatorii activi din platformă</p>
                                        <p class="mt-1 text-xs font-bold text-ochre">0.50 RON / email</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-ochre" id="audience-marketplace-count">~0</p>
                                        <p class="text-xs text-ink-soft">utilizatori</p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Audience Filters (Collapsible) -->
                    <div class="overflow-hidden rounded-xl border-2 border-ink/15">
                        <button type="button" onclick="toggleEmailFilters()" class="flex w-full items-center justify-between p-4 transition hover:bg-paper-2/50">
                            <div class="flex items-center gap-2">
                                <svg id="email-filters-chevron" class="h-4 w-4 -rotate-90 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <p class="font-bold text-ink">Filtrează Audiența</p>
                            </div>
                            <span onclick="event.stopPropagation(); resetEmailFilters()" class="text-sm text-vermilion hover:underline">Resetează filtre</span>
                        </button>
                        <div id="email-filters-body" class="hidden space-y-4 p-4 pt-0">

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Vârsta minimă</label>
                                <select id="email-filter-age-min" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2 text-sm outline-none transition focus:border-ink" onchange="updateEmailAudienceCount()">
                                    <option value="">Orice</option>
                                    <option value="18">18+</option>
                                    <option value="25">25+</option>
                                    <option value="30">30+</option>
                                    <option value="35">35+</option>
                                    <option value="40">40+</option>
                                    <option value="50">50+</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Vârsta maximă</label>
                                <select id="email-filter-age-max" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2 text-sm outline-none transition focus:border-ink" onchange="updateEmailAudienceCount()">
                                    <option value="">Orice</option>
                                    <option value="25">până la 25</option>
                                    <option value="30">până la 30</option>
                                    <option value="35">până la 35</option>
                                    <option value="40">până la 40</option>
                                    <option value="50">până la 50</option>
                                    <option value="65">până la 65</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Gen</label>
                                <select id="email-filter-gender" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2 text-sm outline-none transition focus:border-ink" onchange="updateEmailAudienceCount()">
                                    <option value="">Orice</option>
                                    <option value="male">Bărbați</option>
                                    <option value="female">Femei</option>
                                    <option value="other">Altul</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Oraș</label>
                            <div id="email-filter-city" class="searchable-multiselect" data-placeholder="Caută oraș..."></div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Tip Activitate</label>
                            <div id="email-filter-category" class="searchable-multiselect" data-placeholder="Caută categorie..."></div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Gen Muzical</label>
                            <div id="email-filter-genre" class="searchable-multiselect" data-placeholder="Caută gen muzical..."></div>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-ochre/10 p-3">
                            <div>
                                <p class="text-sm font-bold text-ink">Audiență filtrată</p>
                                <p class="text-xs text-ink-soft">Pe baza filtrelor selectate</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-ochre" id="audience-filtered-count">0</p>
                                <p class="text-xs text-ink-soft">destinatari</p>
                            </div>
                        </div>

                        <div id="filter-breakdowns" class="hidden space-y-2 rounded-lg bg-paper-2 p-3">
                            <p class="mb-1 text-xs font-bold text-ink">Detalii filtre:</p>
                            <div id="breakdown-city" class="hidden">
                                <div id="breakdown-city-items" class="space-y-0.5"></div>
                                <p id="breakdown-without-city" class="mt-1 text-xs text-sky"></p>
                            </div>
                            <div id="breakdown-category" class="hidden">
                                <p id="breakdown-without-category" class="text-xs text-sky"></p>
                            </div>
                            <div id="breakdown-genre" class="hidden">
                                <p id="breakdown-without-genre" class="text-xs text-sky"></p>
                            </div>
                            <div id="breakdown-birthdate" class="hidden">
                                <p id="breakdown-birthdate-text" class="text-xs text-ochre"></p>
                            </div>
                            <div id="partial-matches-toggle" class="mt-2 hidden border-t-2 border-ink/10 pt-2">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" id="include-partial-matches" class="h-4 w-4 accent-ochre" onchange="updateEmailAudienceCount()">
                                    <span class="text-xs text-ink">Include și utilizatorii care se potrivesc parțial (<span id="partial-matches-count">0</span> extra)</span>
                                </label>
                                <p class="ml-6 mt-1 text-[10px] text-ink-soft">Adaugă utilizatorii care corespund celorlalte filtre, dar nu au date pentru filtrele care nu se potrivesc</p>
                            </div>
                        </div>
                        </div><!-- /email-filters-body -->
                    </div>

                    <!-- Cost Summary -->
                    <div class="rounded-xl bg-paper-2 p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm text-ink-soft">Tip audiență:</span>
                            <span class="text-sm font-bold text-ink" id="email-audience-type-label">Clienții Tăi</span>
                        </div>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm text-ink-soft">Cost per email:</span>
                            <span class="font-bold text-ink" id="email-price-per">0.40 RON</span>
                        </div>
                        <div class="mb-2 flex items-center justify-between" id="email-recipient-row-simple">
                            <span class="text-sm text-ink-soft">Nr. destinatari:</span>
                            <span class="font-bold text-ink" id="email-recipient-count">0</span>
                        </div>
                        <div id="email-pricing-breakdown" class="hidden">
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-sm text-ink-soft">Perfect match:</span>
                                <span class="text-sm text-ink"><span id="email-perfect-count">0</span> × <span id="email-perfect-price">0.40</span> = <span class="font-bold" id="email-perfect-cost">0</span> RON</span>
                            </div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm text-ink-soft">Partial match <span class="text-xs">(½ preț)</span>:</span>
                                <span class="text-sm text-ink"><span id="email-partial-count">0</span> × <span id="email-partial-price">0.20</span> = <span class="font-bold" id="email-partial-cost">0</span> RON</span>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t-2 border-ink/10 pt-2">
                            <span class="text-sm font-bold text-ink">Cost total estimat:</span>
                            <span class="text-lg font-bold text-ochre" id="email-cost-estimate">0 RON</span>
                        </div>
                    </div>

                    <div class="rounded-xl bg-sky/10 p-4">
                        <div class="flex gap-3">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <p class="text-sm font-bold text-sky">Confidențialitate garantată</p>
                                <p class="mt-1 text-xs text-ink-soft">Nu vei avea acces la datele personale ale utilizatorilor (nume, email, telefon). Emailurile sunt trimise direct prin platforma noastră.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Data Trimitere *</label>
                            <input type="date" id="email-send-date" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora Trimitere *</label>
                            <input type="time" id="email-send-time" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" value="10:00" required>
                        </div>
                    </div>
                    <p class="text-xs text-ink-soft">Programează trimiterea pentru momentul optim (recomandat: 10:00 sau 18:00)</p>

                    <!-- Email Template Selection -->
                    <div class="mt-4 border-t-2 border-ink/10 pt-4">
                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Alege Model Email</label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="email_template" value="classic" class="peer sr-only" checked>
                                <div class="rounded-xl border-2 border-ink/15 p-3 text-center peer-checked:border-ochre peer-checked:bg-ochre/5">
                                    <div class="mb-2 flex h-16 w-full items-center justify-center rounded-lg bg-gradient-to-b from-ochre/20 to-ochre/5">
                                        <svg class="h-8 w-8 text-ochre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <p class="text-xs font-bold text-ink">Clasic</p>
                                    <p class="text-[10px] text-ink-soft">Imagine + info</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="email_template" value="urgent" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-3 text-center peer-checked:border-ochre peer-checked:bg-ochre/5">
                                    <div class="mb-2 flex h-16 w-full items-center justify-center rounded-lg bg-gradient-to-b from-vermilion/20 to-vermilion/5">
                                        <svg class="h-8 w-8 text-vermilion" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <p class="text-xs font-bold text-ink">Urgent</p>
                                    <p class="text-[10px] text-ink-soft">Ultimele bilete</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="email_template" value="reminder" class="peer sr-only">
                                <div class="rounded-xl border-2 border-ink/15 p-3 text-center peer-checked:border-ochre peer-checked:bg-ochre/5">
                                    <div class="mb-2 flex h-16 w-full items-center justify-center rounded-lg bg-gradient-to-b from-sky/20 to-sky/5">
                                        <svg class="h-8 w-8 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    </div>
                                    <p class="text-xs font-bold text-ink">Reminder</p>
                                    <p class="text-[10px] text-ink-soft">Activitate aproape</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Email Preview Button -->
                    <div class="pt-4">
                        <button type="button" onclick="showEmailPreview()" class="flex w-full items-center justify-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Previzualizează Emailul
                        </button>
                        <p class="mt-1 text-center text-xs text-ink-soft">Vezi cum va arăta emailul înainte de a-l trimite</p>
                    </div>
                </div>

                <!-- Ad Tracking Options -->
                <div id="tracking-options" class="hidden space-y-4">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Platforme de Tracking</label>
                    <p class="-mt-2 text-xs text-ink-soft">Bifează platformele dorite. Pixel ID-ul poate fi completat acum (opțional) sau mai târziu din contul tău.</p>
                    <div class="space-y-3">
                        <div class="rounded-xl border-2 border-ink/15 transition hover:border-sky/50">
                            <label class="flex cursor-pointer items-center gap-3 p-4">
                                <input type="checkbox" name="tracking_platforms[]" value="facebook" class="h-5 w-5 rounded accent-sky" onchange="toggleTrackingPixelField(this, 'facebook')">
                                <span class="grid h-10 w-10 place-items-center rounded-lg bg-sky/10"><svg class="h-6 w-6 text-sky" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></span>
                                <div class="flex-1">
                                    <p class="font-bold text-ink">Facebook Pixel</p>
                                    <p class="text-sm text-ink-soft">Track conversii și retargeting</p>
                                </div>
                                <span class="text-sm font-bold text-sky">49 RON / lună</span>
                            </label>
                            <div id="tracking-pixel-field-facebook" class="hidden border-t-2 border-ink/10 px-4 pb-4 pt-0">
                                <label class="mb-1 mt-3 block text-xs font-bold text-ink">Facebook Pixel ID <span class="font-normal text-ink-soft">(opțional)</span></label>
                                <input type="text" name="tracking_pixel_id_facebook" placeholder="1234567890123456" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" maxlength="50">
                                <p class="mt-1 text-xs text-ink-soft">Lasă gol dacă vrei să-l completezi mai târziu din contul tău.</p>
                            </div>
                        </div>
                        <div class="rounded-xl border-2 border-ink/15 transition hover:border-sky/50">
                            <label class="flex cursor-pointer items-center gap-3 p-4">
                                <input type="checkbox" name="tracking_platforms[]" value="google" class="h-5 w-5 rounded accent-sky" onchange="toggleTrackingPixelField(this, 'google')">
                                <span class="grid h-10 w-10 place-items-center rounded-lg bg-vermilion/10"><svg class="h-6 w-6 text-vermilion" fill="currentColor" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg></span>
                                <div class="flex-1">
                                    <p class="font-bold text-ink">Google Ads</p>
                                    <p class="text-sm text-ink-soft">Conversion tracking complet</p>
                                </div>
                                <span class="text-sm font-bold text-sky">49 RON / lună</span>
                            </label>
                            <div id="tracking-pixel-field-google" class="hidden border-t-2 border-ink/10 px-4 pb-4 pt-0">
                                <label class="mb-1 mt-3 block text-xs font-bold text-ink">Google Ads Conversion ID <span class="font-normal text-ink-soft">(opțional)</span></label>
                                <input type="text" name="tracking_pixel_id_google" placeholder="AW-XXXXXXXXX" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" maxlength="50">
                                <p class="mt-1 text-xs text-ink-soft">Lasă gol dacă vrei să-l completezi mai târziu din contul tău.</p>
                            </div>
                        </div>
                        <div class="rounded-xl border-2 border-ink/15 transition hover:border-sky/50">
                            <label class="flex cursor-pointer items-center gap-3 p-4">
                                <input type="checkbox" name="tracking_platforms[]" value="tiktok" class="h-5 w-5 rounded accent-sky" onchange="toggleTrackingPixelField(this, 'tiktok')">
                                <span class="grid h-10 w-10 place-items-center rounded-lg bg-ink"><svg class="h-6 w-6 text-paper" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/></svg></span>
                                <div class="flex-1">
                                    <p class="font-bold text-ink">TikTok Pixel</p>
                                    <p class="text-sm text-ink-soft">Audiență tânără targetată</p>
                                </div>
                                <span class="text-sm font-bold text-sky">49 RON / lună</span>
                            </label>
                            <div id="tracking-pixel-field-tiktok" class="hidden border-t-2 border-ink/10 px-4 pb-4 pt-0">
                                <label class="mb-1 mt-3 block text-xs font-bold text-ink">TikTok Pixel ID <span class="font-normal text-ink-soft">(opțional)</span></label>
                                <input type="text" name="tracking_pixel_id_tiktok" placeholder="CXXXXXXXXXXXXXXXXX" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" maxlength="50">
                                <p class="mt-1 text-xs text-ink-soft">Lasă gol dacă vrei să-l completezi mai târziu din contul tău.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Durata Abonament</label>
                        <select id="tracking-duration" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                            <option value="1">1 lună</option>
                            <option value="3" selected>3 luni (-10%)</option>
                            <option value="6">6 luni (-15%)</option>
                            <option value="12">12 luni (-25%)</option>
                        </select>
                    </div>
                </div>

                <!-- Ad Campaign Creation Options -->
                <div id="campaign-options" class="hidden space-y-4">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Tip Campanie</label>
                    <div class="space-y-3">
                        <label class="block cursor-pointer">
                            <input type="radio" name="campaign_type" value="basic" class="peer sr-only" checked>
                            <div class="rounded-xl border-2 border-ink/15 p-4 peer-checked:border-sky peer-checked:bg-sky/5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-bold text-ink">Campanie Basic</p>
                                        <ul class="mt-2 space-y-1 text-sm text-ink-soft">
                                            <li>• 1 platformă (FB, Google sau TikTok)</li>
                                            <li>• Design creativ inclus</li>
                                            <li>• Setup &amp; optimizare</li>
                                            <li>• Raport final</li>
                                        </ul>
                                    </div>
                                    <p class="text-lg font-bold text-sky">499 RON</p>
                                </div>
                            </div>
                        </label>
                        <label class="block cursor-pointer">
                            <input type="radio" name="campaign_type" value="standard" class="peer sr-only">
                            <div class="rounded-xl border-2 border-ink/15 p-4 peer-checked:border-sky peer-checked:bg-sky/5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-bold text-ink">Campanie Standard</p>
                                        <ul class="mt-2 space-y-1 text-sm text-ink-soft">
                                            <li>• 2 platforme la alegere</li>
                                            <li>• 3 variante creative</li>
                                            <li>• A/B testing</li>
                                            <li>• Optimizare continuă</li>
                                            <li>• Rapoarte săptămânale</li>
                                        </ul>
                                    </div>
                                    <p class="text-lg font-bold text-sky">899 RON</p>
                                </div>
                            </div>
                        </label>
                        <label class="block cursor-pointer">
                            <input type="radio" name="campaign_type" value="premium" class="peer sr-only">
                            <div class="rounded-xl border-2 border-ink/15 p-4 peer-checked:border-sky peer-checked:bg-sky/5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-bold text-ink">Campanie Premium</p>
                                        <ul class="mt-2 space-y-1 text-sm text-ink-soft">
                                            <li>• Toate cele 3 platforme</li>
                                            <li>• Design video inclus</li>
                                            <li>• Retargeting avansat</li>
                                            <li>• Manager dedicat</li>
                                            <li>• Rapoarte zilnice</li>
                                        </ul>
                                    </div>
                                    <p class="text-lg font-bold text-sky">1,499 RON</p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Buget Publicitar (RON)</label>
                        <input type="number" id="campaign-budget" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" min="500" value="1000" placeholder="Minim 500 RON">
                        <p class="mt-1 text-sm text-ink-soft">Acesta este bugetul pentru plătirea reclamelor (separat de costul serviciului)</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Detalii Suplimentare</label>
                        <textarea id="campaign-notes" class="h-24 w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Spune-ne mai multe despre obiectivele tale..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment -->
            <div id="step-3" class="step-content hidden">
                <div class="mb-6 rounded-xl bg-paper-2 p-6">
                    <h4 class="mb-4 font-bold text-ink">Sumar Comandă</h4>
                    <div id="order-summary" class="space-y-3"></div>
                    <div class="mt-4 border-t-2 border-ink/10 pt-4">
                        <div class="flex items-center justify-between text-lg">
                            <span class="font-bold text-ink">Total de plată:</span>
                            <span class="font-bold text-vermilion" id="order-total">0 RON</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Metoda de plată</label>
                    <div class="space-y-3">
                        <label class="block cursor-pointer">
                            <input type="radio" name="payment_method" value="card" class="peer sr-only" checked>
                            <div class="flex items-center gap-4 rounded-xl border-2 border-ink/15 p-4 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                <div class="flex h-10 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-forest">
                                    <span class="text-[10px] font-bold text-paper">NETOPIA</span>
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-ink">Card Bancar</p>
                                    <p class="text-xs text-ink-soft">Visa, Mastercard, Maestro</p>
                                </div>
                                <div class="flex gap-1">
                                    <div class="flex h-5 w-8 items-center justify-center rounded bg-sky"><span class="text-[8px] font-bold text-paper">VISA</span></div>
                                    <div class="flex h-5 w-8 items-center justify-center rounded bg-vermilion"><span class="text-[6px] font-bold text-paper">MC</span></div>
                                </div>
                            </div>
                        </label>
                        <label class="block cursor-pointer">
                            <input type="radio" name="payment_method" value="transfer" class="peer sr-only">
                            <div class="flex items-center gap-4 rounded-xl border-2 border-ink/15 p-4 peer-checked:border-vermilion peer-checked:bg-vermilion/5">
                                <div class="flex h-10 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-sky/10">
                                    <svg class="h-6 w-6 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-ink">Transfer Bancar</p>
                                    <p class="text-xs text-ink-soft">Activare în 1-2 zile lucrătoare</p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div id="card-payment-fields" class="rounded-xl bg-forest/10 p-4">
                        <p class="text-sm text-forest">Vei fi redirecționat către Netopia Payments pentru a finaliza tranzacția în siguranță.</p>
                    </div>
                    <div id="transfer-payment-info" class="hidden rounded-xl bg-sky/10 p-4">
                        <p class="mb-2 text-sm font-bold text-sky">Detalii pentru transfer bancar:</p>
                        <p class="text-sm text-ink-soft">IBAN: RO49 AAAA 1B31 0075 9384 0000</p>
                        <p class="text-sm text-ink-soft">Banca: BCR</p>
                        <p class="text-sm text-ink-soft">Beneficiar: Bilete Online SRL</p>
                        <p class="mt-2 text-sm text-sky">Serviciul va fi activat după confirmarea plății (1-2 zile lucrătoare).</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="mt-6 flex gap-3 border-t-2 border-ink/10 pt-6">
                <button type="button" id="btn-back" onclick="prevStep()" class="hidden flex-1 items-center justify-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Înapoi
                </button>
                <button type="button" id="btn-next" onclick="nextStep()" class="flex flex-1 items-center justify-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    Continuă
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="submit" id="btn-pay" class="hidden flex-1 items-center justify-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Plătește Acum
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="email-preview-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b-2 border-ink/10 bg-paper p-6">
            <h3 class="font-display text-xl font-bold">Previzualizare Email</h3>
            <button onclick="closeEmailPreview()" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">&times;</button>
        </div>
        <div class="p-6">
            <div class="mb-4 rounded-xl bg-paper-2 p-4">
                <p class="text-sm text-ink-soft">Aceasta este o previzualizare a emailului care va fi trimis. Conținutul final poate varia ușor în funcție de datele activității.</p>
            </div>

            <div class="overflow-hidden rounded-xl border-2 border-ink/15">
                <div class="border-b-2 border-ink/10 bg-paper-2 p-4">
                    <div class="mb-2 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-vermilion">
                            <span class="text-sm font-bold text-paper">B</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-ink" id="preview-sender-name">Bilete Online</p>
                            <p class="text-xs text-ink-soft" id="preview-sender-email">noreply@bilete.online</p>
                        </div>
                    </div>
                    <p class="text-sm"><span class="text-ink-soft">Către:</span> <span class="text-ink" id="preview-recipients">1,250 destinatari</span></p>
                    <p class="mt-1 text-sm"><span class="text-ink-soft">Subiect:</span> <span class="font-medium text-ink" id="preview-subject">🎵 Nu rata activitatea!</span></p>
                </div>

                <div class="bg-paper p-6">
                    <div id="email-preview-content" class="space-y-4"></div>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button onclick="closeEmailPreview()" class="flex-1 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Închide</button>
            </div>
        </div>
    </div>
</div>

<!-- Placement Preview Modal -->
<div id="placement-preview-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b-2 border-ink/10 bg-paper p-6">
            <h3 class="font-display text-xl font-bold" id="placement-preview-title">Previzualizare Plasament</h3>
            <button onclick="closePlacementPreview()" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">&times;</button>
        </div>
        <div class="p-6">
            <div class="mb-6 rounded-xl bg-sky/10 p-4">
                <div class="flex gap-3">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-bold text-sky" id="placement-preview-description">Descriere plasament</p>
                    </div>
                </div>
            </div>

            <div id="placement-preview-content" class="overflow-hidden rounded-xl border-2 border-ink/15"></div>

            <div class="mt-6 flex gap-3">
                <button onclick="closePlacementPreview()" class="flex-1 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Închide</button>
            </div>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
}

let currentStep = 1;
let currentServiceType = '';
let events = [];
let activeServices = [];
let servicePricing = {
    featuring: { home_hero: 120, home_recommendations: 80, category: 60, city: 40 },
    email: { own_per_email: 0.40, marketplace_per_email: 0.50, minimum: 100 },
    tracking: { per_platform_monthly: 49, discounts: { 1: 0, 3: 0.10, 6: 0.15, 12: 0.25 } },
    campaign: { basic: 499, standard: 899, premium: 1499 }
};
let emailAudiences = {
    own: { count: 0, filtered_count: 0 },
    marketplace: { count: 0, filtered_count: 0 }
};
let emailFilterOptions = {
    cities: [],
    categories: [],
    genres: []
};

// ==================== SEARCHABLE MULTISELECT COMPONENT ====================

const multiselectInstances = {};

function initSearchableMultiselect(containerId, options, onChange) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';
    const instance = { selected: new Set(), options, onChange };
    multiselectInstances[containerId] = instance;

    const tagsArea = document.createElement('div');
    tagsArea.className = 'flex flex-wrap gap-1 mb-1';
    tagsArea.id = containerId + '-tags';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2 text-sm outline-none transition focus:border-ink';
    searchInput.placeholder = container.dataset.placeholder || 'Caută...';

    const dropdown = document.createElement('div');
    dropdown.className = 'hidden absolute z-50 w-full mt-1 bg-paper border-2 border-ink/15 rounded-lg shadow-lg max-h-48 overflow-y-auto';
    dropdown.id = containerId + '-dropdown';

    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    wrapper.appendChild(searchInput);
    wrapper.appendChild(dropdown);

    container.appendChild(tagsArea);
    container.appendChild(wrapper);

    function renderDropdown(filter = '') {
        dropdown.innerHTML = '';
        const lowerFilter = filter.toLowerCase();
        let hasResults = false;
        options.forEach(opt => {
            if (lowerFilter && !opt.label.toLowerCase().includes(lowerFilter)) return;
            hasResults = true;
            const item = document.createElement('div');
            item.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-ochre/10 flex items-center gap-2 ' +
                (instance.selected.has(opt.value) ? 'bg-ochre/5 text-ochre font-medium' : 'text-ink');
            const check = instance.selected.has(opt.value) ? '<svg class="flex-shrink-0 w-4 h-4 text-ochre" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : '<span class="flex-shrink-0 w-4 h-4"></span>';
            item.innerHTML = check + '<span>' + escHtml(opt.label.trim()) + '</span>';
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                if (instance.selected.has(opt.value)) {
                    instance.selected.delete(opt.value);
                } else {
                    instance.selected.add(opt.value);
                }
                renderTags();
                renderDropdown(searchInput.value);
                if (onChange) onChange();
            });
            dropdown.appendChild(item);
        });
        if (!hasResults) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-2 text-sm text-ink-soft';
            empty.textContent = 'Niciun rezultat';
            dropdown.appendChild(empty);
        }
    }

    function renderTags() {
        tagsArea.innerHTML = '';
        instance.selected.forEach(val => {
            const opt = options.find(o => o.value === val);
            if (!opt) return;
            const tag = document.createElement('span');
            tag.className = 'inline-flex items-center gap-1 px-2 py-0.5 bg-ochre/10 text-ochre text-xs rounded-md';
            tag.innerHTML = '<span>' + escHtml(opt.label.trim()) + '</span><button type="button" class="hover:text-vermilion">&times;</button>';
            tag.querySelector('button').addEventListener('click', (e) => {
                e.stopPropagation();
                instance.selected.delete(val);
                renderTags();
                renderDropdown(searchInput.value);
                if (onChange) onChange();
            });
            tagsArea.appendChild(tag);
        });
    }

    searchInput.addEventListener('focus', () => {
        renderDropdown(searchInput.value);
        dropdown.classList.remove('hidden');
    });
    searchInput.addEventListener('input', () => {
        renderDropdown(searchInput.value);
        dropdown.classList.remove('hidden');
    });
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    renderDropdown();
}

function getMultiselectValues(containerId) {
    const instance = multiselectInstances[containerId];
    return instance ? Array.from(instance.selected) : [];
}

function clearMultiselect(containerId) {
    const instance = multiselectInstances[containerId];
    if (instance) {
        instance.selected.clear();
        const tagsArea = document.getElementById(containerId + '-tags');
        if (tagsArea) tagsArea.innerHTML = '';
    }
}

// ==================== END SEARCHABLE MULTISELECT ====================

document.addEventListener('DOMContentLoaded', function() {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadPricing();
    loadEvents();
    loadActiveServices();
    loadStats();
    loadEmailFilterOptions();
    setupPaymentMethodToggle();
    setupEmailAudienceToggle();
    setupDateValidation();
    checkUrlParams();

    document.querySelectorAll('input[name="email_template"]').forEach(radio => {
        radio.addEventListener('change', () => { lockedVariants = {}; });
    });

    document.getElementById('service-event').addEventListener('change', function() {
        const event = events.find(e => e.id == this.value);
        if (event) {
            document.getElementById('event-preview').classList.remove('hidden');
            document.getElementById('event-image').src = getStorageUrl(event.image);
            document.getElementById('event-title').textContent = event.name || event.title || '';
            const eventDate = event.starts_at || event.date;
            document.getElementById('event-date').textContent = eventDate ? BileteOnlineUtils.formatDate(eventDate) : '';
            document.getElementById('event-venue').textContent = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || '';
            updateEmailAudienceCount();
        } else {
            document.getElementById('event-preview').classList.add('hidden');
        }
    });

    document.getElementById('service-form').addEventListener('submit', onServiceFormSubmit);

    document.getElementById('service-filter').addEventListener('change', function() {
        const type = this.value;
        const filtered = type ? activeServices.filter(s => s.type === type) : activeServices;
        const temp = activeServices;
        activeServices = filtered;
        renderActiveServices();
        activeServices = temp;
    });
});

function checkUrlParams() {
    const params = new URLSearchParams(window.location.search);

    if (params.get('featuring_activated') === '1') {
        showSuccessBanner('Promovare Activată!', 'Activitatea ta este acum afișată în secțiunile selectate.');
    } else if (params.get('payment_success') === '1' || params.get('payment') === 'success') {
        showSuccessBanner('Plata Confirmată!', 'Serviciul a fost activat cu succes. Activitatea ta va apărea în secțiunile selectate.');
    }

    if (params.get('cancelled') === '1' || params.get('payment') === 'cancel') {
        document.getElementById('cancelled-banner').classList.remove('hidden');
    }

    if (params.toString()) {
        window.history.replaceState({}, '', window.location.pathname);
    }
}

function showSuccessBanner(title, message) {
    document.getElementById('success-title').textContent = title;
    document.getElementById('success-message').textContent = message;
    document.getElementById('success-banner').classList.remove('hidden');
}

function closeSuccessBanner() {
    document.getElementById('success-banner').classList.add('hidden');
}

function closeCancelledBanner() {
    document.getElementById('cancelled-banner').classList.add('hidden');
}

async function loadPricing() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/services/pricing');
        if (response.success && response.data?.pricing) {
            servicePricing = response.data.pricing;
            updatePricingUI();
        } else if (response.success && response.data) {
            servicePricing = response.data;
            updatePricingUI();
        }
    } catch (e) {
        console.log('Using default pricing');
        updatePricingUI();
    }
}

function updatePricingUI() {
    const cardEmailPrice = document.getElementById('card-email-price');
    if (cardEmailPrice) {
        const lowestEmailPrice = Math.min(
            servicePricing.email.own_per_email || 0.40,
            servicePricing.email.marketplace_per_email || 0.50
        );
        cardEmailPrice.textContent = BileteOnlineUtils.formatCurrency(lowestEmailPrice);
    }

    const ownPriceEl = document.querySelector('#email-options input[value="own"]')?.closest('label')?.querySelector('.text-ochre.font-bold');
    if (ownPriceEl) {
        ownPriceEl.textContent = BileteOnlineUtils.formatCurrency(servicePricing.email.own_per_email || 0.40) + ' / email';
    }
    const marketplacePriceEl = document.querySelector('#email-options input[value="marketplace"]')?.closest('label')?.querySelector('.text-ochre.font-bold');
    if (marketplacePriceEl) {
        marketplacePriceEl.textContent = BileteOnlineUtils.formatCurrency(servicePricing.email.marketplace_per_email || 0.50) + ' / email';
    }

    const fp = servicePricing.featuring || {};
    document.querySelectorAll('#featuring-options input[name="featuring_locations[]"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('[data-price-key]');
        if (priceEl) {
            const key = priceEl.getAttribute('data-price-key');
            const price = fp[key] ?? fp[input.value];
            priceEl.textContent = price != null ? price + ' RON / zi' : '— RON / zi';
        }
    });

    const cardFeaturingPrice = document.getElementById('card-featuring-price');
    if (cardFeaturingPrice) {
        const lowestFeaturing = Math.min(...Object.values(fp).filter(v => typeof v === 'number' && v > 0));
        if (isFinite(lowestFeaturing)) {
            cardFeaturingPrice.textContent = lowestFeaturing + ' RON';
        }
    }

    document.querySelectorAll('#tracking-options input[name="tracking_platforms[]"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('.text-sky');
        if (priceEl && servicePricing.tracking.per_platform_monthly) {
            priceEl.textContent = servicePricing.tracking.per_platform_monthly + ' RON / lună';
        }
    });

    const campaignPrices = servicePricing.campaign;
    document.querySelectorAll('#campaign-options input[name="campaign_type"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('.text-sky');
        if (priceEl && campaignPrices[input.value]) {
            priceEl.textContent = BileteOnlineUtils.formatCurrency(campaignPrices[input.value]);
        }
    });

    updateEmailAudienceUI();
}

function toggleTrackingPixelField(checkbox, platform) {
    const field = document.getElementById('tracking-pixel-field-' + platform);
    if (!field) return;
    field.classList.toggle('hidden', !checkbox.checked);
    if (!checkbox.checked) {
        const input = field.querySelector('input[type="text"]');
        if (input) input.value = '';
    }
}

function setupDateValidation() {
    const startInput = document.getElementById('featuring-start');
    const endInput = document.getElementById('featuring-end');

    const today = new Date().toISOString().split('T')[0];
    startInput.min = today;
    endInput.min = today;

    startInput.addEventListener('change', function() {
        if (this.value) {
            endInput.min = this.value;
            if (endInput.value && endInput.value < this.value) {
                endInput.value = this.value;
            }
        }
    });

    endInput.addEventListener('change', function() {
        if (startInput.value && this.value < startInput.value) {
            orgNotify('Data de sfârșit trebuie să fie după data de început', 'error');
            this.value = startInput.value;
        }
    });
}

async function loadEvents() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/events');
        if (response.success && response.data) {
            const allEvents = Array.isArray(response.data) ? response.data : [];
            events = allEvents.filter(e => e.is_editable !== false && e.is_past !== true && !e.is_cancelled);

            const select = document.getElementById('service-event');

            if (events.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Momentan nu ai activități în derulare pentru care să faci promovare';
                opt.disabled = true;
                select.appendChild(opt);
                return;
            }

            events.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.name || e.title;
                opt.dataset.image = e.image;
                opt.dataset.date = e.starts_at || e.date;
                let venueName = e.venue_name || (typeof e.venue === 'object' && e.venue?.name) || (typeof e.venue === 'string' ? e.venue : '') || e.venue_city || '';
                opt.dataset.venue = venueName;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.log('Events will load when API is available', e);
    }
}

async function loadActiveServices() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/services/orders');
        if (response.success) {
            activeServices = Array.isArray(response.data) ? response.data : (response.data?.data || []);
            renderActiveServices();
        }
    } catch (e) {
        activeServices = [];
        renderActiveServices();
    }
}

async function loadStats() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/services/stats');
        if (response.success) {
            const ac = document.getElementById('active-services');
            if (ac) ac.textContent = response.data.active_count || 0;
            const tv = document.getElementById('total-views');
            if (tv) tv.textContent = BileteOnlineUtils.formatNumber(response.data.total_views || 0);
            const es = document.getElementById('emails-sent');
            if (es) es.textContent = BileteOnlineUtils.formatNumber(response.data.emails_sent || 0);
            const ts = document.getElementById('total-spent');
            if (ts) ts.textContent = BileteOnlineUtils.formatCurrency(response.data.total_spent || 0);
        }
    } catch (e) {
        // Stats will load when API is available
    }
}

const SERVICE_TYPE_BADGE = {
    featuring: 'bg-vermilion/10 text-vermilion',
    email: 'bg-ochre/10 text-ochre',
    tracking: 'bg-sky/10 text-sky',
    campaign: 'bg-sky/10 text-sky'
};

const SERVICE_STATUS_BADGE = {
    active: 'bg-forest/10 text-forest',
    pending_payment: 'bg-ochre/10 text-ochre',
    pending: 'bg-ochre/10 text-ochre',
    completed: 'bg-ink/10 text-ink-soft',
    cancelled: 'bg-ink/10 text-ink-soft'
};

function renderActiveServices() {
    const container = document.getElementById('services-list');
    if (!activeServices.length) {
        container.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-ink-soft">Nu ai servicii active</td></tr>';
        return;
    }

    const typeLabels = {
        featuring: 'Promovare',
        email: 'Email Marketing',
        tracking: 'Ad Tracking',
        campaign: 'Campanie Ads'
    };

    const statusMap = {
        active: { label: 'Activ' },
        pending_payment: { label: 'Așteaptă plata' },
        pending: { label: 'În procesare' },
        completed: { label: 'Finalizat' },
        cancelled: { label: 'Anulat' },
    };

    const platformLabelsShort = { facebook: 'Facebook', google: 'Google Ads', tiktok: 'TikTok' };

    container.innerHTML = activeServices.map(s => {
        const statusInfo = statusMap[s.status] || { label: s.status };
        const needsPixel = !!s.needs_pixel_setup;
        const missingPlatforms = (s.missing_pixel_platforms || []).map(p => platformLabelsShort[p] || p).join(', ');
        const pixelAlert = needsPixel
            ? `<a href="/organizator/services/${s.id}" class="block mt-2 text-xs px-2 py-1.5 rounded-lg bg-ochre/10 border-2 border-ochre/30 text-ochre hover:bg-ochre/20 transition-colors" title="Click pentru a completa Pixel ID">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <span class="font-bold">Necesită Pixel ID${missingPlatforms ? ': ' + escHtml(missingPlatforms) : ''}</span>
                </span>
            </a>`
            : '';
        const typeBadge = SERVICE_TYPE_BADGE[s.type] || 'bg-ink/10 text-ink-soft';
        const statusBadge = SERVICE_STATUS_BADGE[s.status] || 'bg-ink/10 text-ink-soft';
        return `
        <tr class="hover:bg-paper-2/50">
            <td class="px-6 py-4 align-top">
                <span class="px-3 py-1 ${typeBadge} text-sm font-bold rounded-full">${typeLabels[s.type] || s.type}</span>
                ${pixelAlert}
            </td>
            <td class="px-6 py-4 font-medium text-ink align-top">${escHtml(s.event_name) || '-'}</td>
            <td class="px-6 py-4 text-sm text-ink-soft align-top">${escHtml(s.details) || '-'}</td>
            <td class="px-6 py-4 text-sm text-ink-soft align-top">${BileteOnlineUtils.formatDate(s.service_start_date)} - ${BileteOnlineUtils.formatDate(s.service_end_date)}</td>
            <td class="px-6 py-4 align-top">
                <span class="px-3 py-1 ${statusBadge} text-sm rounded-full">${statusInfo.label}</span>
            </td>
            <td class="px-6 py-4 text-right align-top">
                <button onclick="viewServiceDetails('${s.id}')" class="p-2 text-ink-soft hover:text-ink">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </td>
        </tr>
    `;
    }).join('');
}

function openServiceModal(type) {
    currentServiceType = type;
    currentStep = 1;
    document.getElementById('service-type').value = type;

    const titles = {
        featuring: 'Promovare Activitate',
        email: 'Campanie Email Marketing',
        tracking: 'Tracking Campanii Ads',
        campaign: 'Creare Campanie Ads'
    };
    document.getElementById('modal-title').textContent = titles[type];

    document.getElementById('service-form').reset();
    updateStepUI();

    document.getElementById('service-modal').classList.remove('hidden');
    document.getElementById('service-modal').classList.add('flex');
}

function closeServiceModal() {
    document.getElementById('service-modal').classList.add('hidden');
    document.getElementById('service-modal').classList.remove('flex');
}

function updateStepUI() {
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step-${i}-indicator`);
        const circle = indicator.querySelector('div');
        const text = indicator.querySelector('span');

        if (i < currentStep) {
            circle.className = 'w-8 h-8 rounded-full bg-forest text-paper flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-bold text-forest';
        } else if (i === currentStep) {
            circle.className = 'w-8 h-8 rounded-full bg-vermilion text-paper flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-bold text-ink';
        } else {
            circle.className = 'w-8 h-8 rounded-full bg-ink/15 text-ink-soft flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-medium text-ink-soft';
        }
    }

    document.querySelectorAll('.step-content').forEach((el, i) => {
        el.classList.toggle('hidden', i + 1 !== currentStep);
    });

    if (currentStep === 2) {
        document.getElementById('featuring-options').classList.toggle('hidden', currentServiceType !== 'featuring');
        document.getElementById('email-options').classList.toggle('hidden', currentServiceType !== 'email');
        document.getElementById('tracking-options').classList.toggle('hidden', currentServiceType !== 'tracking');
        document.getElementById('campaign-options').classList.toggle('hidden', currentServiceType !== 'campaign');
    }

    document.getElementById('btn-back').classList.toggle('hidden', currentStep === 1);
    document.getElementById('btn-next').classList.toggle('hidden', currentStep === 3);
    document.getElementById('btn-pay').classList.toggle('hidden', currentStep !== 3);
}

function nextStep() {
    if (currentStep === 1) {
        const eventId = document.getElementById('service-event').value;
        if (!eventId) {
            orgNotify('Selectează o activitate', 'error');
            return;
        }
        const event = events.find(e => e.id == eventId);
        if (event) {
            document.getElementById('event-preview').classList.remove('hidden');
            document.getElementById('event-image').src = getStorageUrl(event.image);
            document.getElementById('event-title').textContent = event.name || event.title || '';
            const eventDate = event.starts_at || event.date;
            document.getElementById('event-date').textContent = eventDate ? BileteOnlineUtils.formatDate(eventDate) : '';
            document.getElementById('event-venue').textContent = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || '';
        }
    }

    if (currentStep === 2) {
        if (!validateStep2()) return;
        calculateOrderSummary();
    }

    if (currentStep < 3) {
        currentStep++;
        updateStepUI();
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepUI();
    }
}

function validateStep2() {
    switch (currentServiceType) {
        case 'featuring':
            const locations = document.querySelectorAll('input[name="featuring_locations[]"]:checked');
            if (!locations.length) {
                orgNotify('Selectează cel puțin o locație', 'error');
                return false;
            }
            const startDate = document.getElementById('featuring-start').value;
            const endDate = document.getElementById('featuring-end').value;
            if (!startDate || !endDate) {
                orgNotify('Selectează perioada de promovare', 'error');
                return false;
            }
            const today = new Date().toISOString().split('T')[0];
            if (startDate < today) {
                orgNotify('Data de început nu poate fi în trecut', 'error');
                return false;
            }
            if (endDate <= startDate) {
                orgNotify('Data de sfârșit trebuie să fie după data de început', 'error');
                return false;
            }
            break;
        case 'email':
            const emailAudienceTypeVal = document.querySelector('input[name="email_audience"]:checked').value;
            const recipientCount = emailAudiences[emailAudienceTypeVal]?.filtered_count || 0;
            if (recipientCount < 1) {
                orgNotify('Nu există destinatari pentru filtrele selectate', 'error');
                return false;
            }
            const sendDate = document.getElementById('email-send-date').value;
            const sendTime = document.getElementById('email-send-time').value;
            if (!sendDate) {
                orgNotify('Selectează data trimiterii', 'error');
                return false;
            }
            if (!sendTime) {
                orgNotify('Selectează ora trimiterii', 'error');
                return false;
            }
            break;
        case 'tracking':
            const platforms = document.querySelectorAll('input[name="tracking_platforms[]"]:checked');
            if (!platforms.length) {
                orgNotify('Selectează cel puțin o platformă', 'error');
                return false;
            }
            break;
        case 'campaign':
            const budget = parseInt(document.getElementById('campaign-budget').value);
            if (isNaN(budget) || budget < 500) {
                orgNotify('Bugetul minim este 500 RON', 'error');
                return false;
            }
            break;
    }
    return true;
}

function calculateOrderSummary() {
    const summary = document.getElementById('order-summary');
    let total = 0;
    let items = [];

    const event = events.find(e => e.id == document.getElementById('service-event').value);

    switch (currentServiceType) {
        case 'featuring': {
            const locations = document.querySelectorAll('input[name="featuring_locations[]"]:checked');
            const startVal = document.getElementById('featuring-start').value;
            const endVal = document.getElementById('featuring-end').value;

            const startDate = new Date(startVal + 'T00:00:00');
            const endDate = new Date(endVal + 'T00:00:00');
            const daysMultiplier = Math.max(Math.round((endDate - startDate) / (1000 * 60 * 60 * 24)), 1);
            const daysDisplay = daysMultiplier;

            const prices = servicePricing.featuring;
            const labels = {
                home_hero: 'Prima pagină - Hero',
                home_recommendations: 'Prima pagină - Recomandări',
                category: 'Pagina categorie activitate',
                city: 'Pagina oraș activitate'
            };

            locations.forEach(loc => {
                const price = (prices[loc.value] ?? 40) * daysMultiplier;
                items.push({ name: (labels[loc.value] || loc.value) + ' (' + daysDisplay + ' zile)', price });
                total += price;
            });
            break;
        }

        case 'email':
            const emailAudienceType = document.querySelector('input[name="email_audience"]:checked').value;
            const emailAudienceLabels = { own: 'Clienții Tăi', marketplace: 'Baza Marketplace' };
            const emailCount = emailAudiences[emailAudienceType]?.filtered_count || 0;
            const emailPricePerUnit = emailAudienceType === 'own'
                ? (servicePricing.email.own_per_email || 0.40)
                : (servicePricing.email.marketplace_per_email || 0.50);
            const emailTotalPrice = emailCount * emailPricePerUnit;
            items.push({
                name: 'Campanie Email - ' + emailAudienceLabels[emailAudienceType],
                price: null
            });
            items.push({
                name: BileteOnlineUtils.formatNumber(emailCount) + ' destinatari x ' + BileteOnlineUtils.formatCurrency(emailPricePerUnit),
                price: emailTotalPrice
            });
            total = emailTotalPrice;
            break;

        case 'tracking':
            const trackingPlatforms = document.querySelectorAll('input[name="tracking_platforms[]"]:checked');
            const duration = parseInt(document.getElementById('tracking-duration').value);
            const discounts = servicePricing.tracking.discounts || { 1: 0, 3: 0.1, 6: 0.15, 12: 0.25 };
            const platformPrice = servicePricing.tracking.per_platform_monthly || 49;

            trackingPlatforms.forEach(p => {
                const platformLabels = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
                let price = platformPrice * duration * (1 - (discounts[duration] || 0));
                items.push({ name: platformLabels[p.value] + ' (' + duration + ' luni)', price });
                total += price;
            });
            break;

        case 'campaign':
            const campaignType = document.querySelector('input[name="campaign_type"]:checked').value;
            const campaignPrices = servicePricing.campaign;
            const campaignLabels = { basic: 'Campanie Basic', standard: 'Campanie Standard', premium: 'Campanie Premium' };
            items.push({ name: campaignLabels[campaignType], price: campaignPrices[campaignType] || 499 });
            total = campaignPrices[campaignType] || 499;
            break;
    }

    const eventName = event ? (event.name || event.title || '') : '';
    let eventCategory = '';
    if (event?.category_name) {
        eventCategory = event.category_name;
    } else if (event?.category) {
        if (typeof event.category === 'string') {
            eventCategory = event.category;
        } else if (typeof event.category === 'object') {
            eventCategory = event.category?.name || event.category?.label || '';
        }
    }

    summary.innerHTML = `
        <div class="flex justify-between text-sm">
            <span class="text-ink-soft">Activitate:</span>
            <span class="font-medium text-ink">${escHtml(eventName)}</span>
        </div>
        ${eventCategory ? `
        <div class="flex justify-between text-sm">
            <span class="text-ink-soft">Categorie:</span>
            <span class="font-medium text-ink">${escHtml(eventCategory)}</span>
        </div>
        ` : ''}
        ${items.map(item => `
            <div class="flex justify-between text-sm">
                <span class="text-ink-soft">${escHtml(item.name)}</span>
                <span class="font-medium text-ink">${BileteOnlineUtils.formatCurrency(item.price)}</span>
            </div>
        `).join('')}
    `;

    document.getElementById('order-total').textContent = BileteOnlineUtils.formatCurrency(total);
}

function setupPaymentMethodToggle() {
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('card-payment-fields').classList.toggle('hidden', this.value !== 'card');
            document.getElementById('transfer-payment-info').classList.toggle('hidden', this.value !== 'transfer');
        });
    });
}

async function loadEmailFilterOptions() {
    try {
        const citiesRes = await BileteOnlineAPI.get('/cities', { per_page: 200 });
        const cities = citiesRes?.data || citiesRes?.cities || [];
        if (Array.isArray(cities) && cities.length > 0) {
            emailFilterOptions.cities = cities;
            initSearchableMultiselect('email-filter-city', cities.map(c => ({
                value: c.name,
                label: c.name
            })), updateEmailAudienceCount);
        }
    } catch (e) {
        console.log('Failed to load cities:', e.message);
    }

    try {
        const catRes = await BileteOnlineAPI.get('/event-categories');
        const categories = catRes?.categories || catRes?.data?.categories || catRes?.data || [];
        if (Array.isArray(categories) && categories.length > 0) {
            emailFilterOptions.categories = categories;
            const flatCats = [];
            categories.forEach(cat => {
                flatCats.push({ value: String(cat.id), label: cat.name });
                if (cat.children) {
                    cat.children.forEach(child => {
                        flatCats.push({ value: String(child.id), label: '  ' + child.name });
                    });
                }
            });
            initSearchableMultiselect('email-filter-category', flatCats, updateEmailAudienceCount);
        }
    } catch (e) {
        console.log('Failed to load categories:', e.message);
    }

    try {
        const genreRes = await BileteOnlineAPI.get('/event-genres');
        const genres = genreRes?.genres || genreRes?.data?.genres || genreRes?.data || [];
        if (Array.isArray(genres) && genres.length > 0) {
            emailFilterOptions.genres = genres;
            initSearchableMultiselect('email-filter-genre', genres.map(g => ({
                value: String(g.id),
                label: g.name
            })), updateEmailAudienceCount);
        }
    } catch (e) {
        console.log('Failed to load genres:', e.message);
    }

    updateEmailAudienceCount();
    loadInitialAudienceCounts();
}

async function loadInitialAudienceCounts() {
    try {
        const [ownRes, mpRes] = await Promise.all([
            BileteOnlineAPI.get('/organizer/services/email-audiences', { audience_type: 'own' }),
            BileteOnlineAPI.get('/organizer/services/email-audiences', { audience_type: 'marketplace' })
        ]);
        if (ownRes?.success && ownRes.data) {
            emailAudiences.own.count = ownRes.data.total_count || 0;
            document.getElementById('audience-own-count').textContent = BileteOnlineUtils.formatNumber(ownRes.data.total_count || 0);
        }
        if (mpRes?.success && mpRes.data) {
            emailAudiences.marketplace.count = mpRes.data.total_count || 0;
            document.getElementById('audience-marketplace-count').textContent = '~' + BileteOnlineUtils.formatNumber(mpRes.data.total_count || 0);
        }
    } catch (e) {
        console.log('Failed to load initial audience counts:', e.message);
    }
}

function setupEmailAudienceToggle() {
    document.querySelectorAll('input[name="email_audience"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateEmailAudienceUI();
            updateEmailAudienceCount();
        });
    });
}

function updateEmailAudienceUI() {
    const audienceType = document.querySelector('input[name="email_audience"]:checked')?.value || 'own';
    const isOwn = audienceType === 'own';

    document.getElementById('email-audience-type-label').textContent = isOwn ? 'Clienții Tăi' : 'Baza Marketplace';
    document.getElementById('email-price-per').textContent = isOwn
        ? BileteOnlineUtils.formatCurrency(servicePricing.email.own_per_email || 0.40)
        : BileteOnlineUtils.formatCurrency(servicePricing.email.marketplace_per_email || 0.50);
}

async function updateEmailAudienceCount() {
    const audienceType = document.querySelector('input[name="email_audience"]:checked')?.value || 'own';
    const eventId = document.getElementById('service-event').value;

    const cities = getMultiselectValues('email-filter-city');
    const categories = getMultiselectValues('email-filter-category');
    const genres = getMultiselectValues('email-filter-genre');

    const gender = document.getElementById('email-filter-gender').value || null;

    const filters = {
        audience_type: audienceType,
        event_id: eventId,
        age_min: document.getElementById('email-filter-age-min').value || null,
        age_max: document.getElementById('email-filter-age-max').value || null,
        gender: gender,
        cities: cities.length > 0 ? cities : null,
        categories: categories.length > 0 ? categories : null,
        genres: genres.length > 0 ? genres : null
    };

    Object.keys(filters).forEach(k => filters[k] === null && delete filters[k]);

    try {
        const response = await BileteOnlineAPI.get('/organizer/services/email-audiences', filters);
        if (response.success && response.data) {
            let count = response.data.filtered_count || 0;
            const baseCount = response.data.total_count || 0;
            const fc = response.data.filter_counts || {};

            let partialExtra = 0;
            const maxWithout = Math.max(
                fc.without_city || 0,
                fc.without_category || 0,
                fc.without_genre || 0
            );
            partialExtra = Math.max(0, maxWithout - count);

            const includePartial = document.getElementById('include-partial-matches')?.checked;
            const perfectCount = count;
            let partialCount = 0;
            if (includePartial && partialExtra > 0) {
                partialCount = partialExtra;
                count = perfectCount + partialCount;
            }

            document.getElementById('audience-filtered-count').textContent = BileteOnlineUtils.formatNumber(count);
            document.getElementById('email-recipient-count').textContent = BileteOnlineUtils.formatNumber(count);

            if (audienceType === 'own') {
                document.getElementById('audience-own-count').textContent = BileteOnlineUtils.formatNumber(baseCount);
                emailAudiences.own.count = baseCount;
                emailAudiences.own.filtered_count = count;
                emailAudiences.own.perfect_count = perfectCount;
                emailAudiences.own.partial_count = partialCount;
            } else {
                document.getElementById('audience-marketplace-count').textContent = '~' + BileteOnlineUtils.formatNumber(baseCount);
                emailAudiences.marketplace.count = baseCount;
                emailAudiences.marketplace.filtered_count = count;
                emailAudiences.marketplace.perfect_count = perfectCount;
                emailAudiences.marketplace.partial_count = partialCount;
            }

            const pricePerEmail = audienceType === 'own'
                ? (servicePricing.email.own_per_email || 0.40)
                : (servicePricing.email.marketplace_per_email || 0.50);
            const partialPrice = Math.round((pricePerEmail / 2) * 100) / 100;
            const perfectCost = perfectCount * pricePerEmail;
            const partialCostVal = Math.round(partialCount * partialPrice * 100) / 100;
            const totalCost = perfectCost + partialCostVal;
            document.getElementById('email-cost-estimate').textContent = BileteOnlineUtils.formatCurrency(totalCost);

            if (includePartial && partialCount > 0) {
                document.getElementById('email-recipient-row-simple').classList.add('hidden');
                document.getElementById('email-pricing-breakdown').classList.remove('hidden');
                document.getElementById('email-perfect-count').textContent = BileteOnlineUtils.formatNumber(perfectCount);
                document.getElementById('email-perfect-price').textContent = pricePerEmail.toFixed(2);
                document.getElementById('email-perfect-cost').textContent = BileteOnlineUtils.formatCurrency(perfectCost);
                document.getElementById('email-partial-count').textContent = BileteOnlineUtils.formatNumber(partialCount);
                document.getElementById('email-partial-price').textContent = partialPrice.toFixed(2);
                document.getElementById('email-partial-cost').textContent = BileteOnlineUtils.formatCurrency(partialCostVal);
            } else {
                document.getElementById('email-recipient-row-simple').classList.remove('hidden');
                document.getElementById('email-pricing-breakdown').classList.add('hidden');
            }

            displayFilterBreakdowns(fc, baseCount, partialExtra);
        }
    } catch (e) {
        console.log('Audience count error:', e.message);
        const count = emailAudiences[audienceType]?.filtered_count || 0;
        document.getElementById('audience-filtered-count').textContent = BileteOnlineUtils.formatNumber(count);
        document.getElementById('email-recipient-count').textContent = BileteOnlineUtils.formatNumber(count);

        const pricePerEmail = audienceType === 'own'
            ? (servicePricing.email.own_per_email || 0.40)
            : (servicePricing.email.marketplace_per_email || 0.50);
        document.getElementById('email-cost-estimate').textContent = BileteOnlineUtils.formatCurrency(count * pricePerEmail);
    }
}

function toggleEmailFilters() {
    const body = document.getElementById('email-filters-body');
    const chevron = document.getElementById('email-filters-chevron');
    body.classList.toggle('hidden');
    chevron.classList.toggle('-rotate-90');
}

function resetEmailFilters() {
    document.getElementById('email-filter-age-min').value = '';
    document.getElementById('email-filter-age-max').value = '';
    document.getElementById('email-filter-gender').value = '';
    clearMultiselect('email-filter-city');
    clearMultiselect('email-filter-category');
    clearMultiselect('email-filter-genre');
    const partialCheckbox = document.getElementById('include-partial-matches');
    if (partialCheckbox) partialCheckbox.checked = false;
    updateEmailAudienceCount();
}

function displayFilterBreakdowns(filterCounts, totalCount, partialExtra) {
    const container = document.getElementById('filter-breakdowns');
    const hasData = filterCounts && Object.keys(filterCounts).length > 0;

    if (!hasData) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');

    const citySection = document.getElementById('breakdown-city');
    const cityItems = document.getElementById('breakdown-city-items');
    const withoutCityEl = document.getElementById('breakdown-without-city');
    if (filterCounts.by_city && typeof filterCounts.by_city === 'object' && Object.keys(filterCounts.by_city).length > 0) {
        citySection.classList.remove('hidden');
        cityItems.innerHTML = '';
        for (const [cityKey, count] of Object.entries(filterCounts.by_city)) {
            if (cityKey === undefined || cityKey === 'undefined') continue;
            const div = document.createElement('div');
            div.className = 'flex justify-between text-xs';
            const cityName = String(cityKey);
            const countStr = typeof count === 'number' ? BileteOnlineUtils.formatNumber(count) : '0';
            div.innerHTML = '<span class="text-ink-soft">' + escHtml(cityName) + '</span><span class="font-medium text-ink">' + countStr + '</span>';
            cityItems.appendChild(div);
        }
        if (filterCounts.without_city !== undefined) {
            withoutCityEl.textContent = 'Fără filtru oraș: ' + BileteOnlineUtils.formatNumber(filterCounts.without_city) + ' se potrivesc parțial';
            withoutCityEl.classList.remove('hidden');
        } else {
            withoutCityEl.classList.add('hidden');
        }
    } else {
        citySection.classList.add('hidden');
    }

    const catSection = document.getElementById('breakdown-category');
    const withoutCatEl = document.getElementById('breakdown-without-category');
    if (filterCounts.without_category !== undefined) {
        catSection.classList.remove('hidden');
        withoutCatEl.textContent = 'Fără filtru categorie: ' + BileteOnlineUtils.formatNumber(filterCounts.without_category) + ' se potrivesc parțial';
    } else {
        catSection.classList.add('hidden');
    }

    const genreSection = document.getElementById('breakdown-genre');
    const withoutGenreEl = document.getElementById('breakdown-without-genre');
    if (filterCounts.without_genre !== undefined) {
        genreSection.classList.remove('hidden');
        withoutGenreEl.textContent = 'Fără filtru gen muzical: ' + BileteOnlineUtils.formatNumber(filterCounts.without_genre) + ' se potrivesc parțial';
    } else {
        genreSection.classList.add('hidden');
    }

    const bdSection = document.getElementById('breakdown-birthdate');
    const bdText = document.getElementById('breakdown-birthdate-text');
    if (filterCounts.with_birth_date !== undefined && totalCount > 0) {
        const pct = Math.round((filterCounts.with_birth_date / totalCount) * 100);
        bdSection.classList.remove('hidden');
        bdText.textContent = BileteOnlineUtils.formatNumber(filterCounts.with_birth_date) + ' din ' + BileteOnlineUtils.formatNumber(totalCount) + ' (' + pct + '%) au data nașterii setată (relevantă pentru filtru vârstă)';
    } else {
        bdSection.classList.add('hidden');
    }

    const partialToggle = document.getElementById('partial-matches-toggle');
    if (partialExtra > 0) {
        partialToggle.classList.remove('hidden');
        document.getElementById('partial-matches-count').textContent = BileteOnlineUtils.formatNumber(partialExtra);
    } else {
        partialToggle.classList.add('hidden');
    }
}

async function onServiceFormSubmit(e) {
    e.preventDefault();

    const payBtn = document.getElementById('btn-pay');
    const originalBtnText = payBtn.innerHTML;
    payBtn.disabled = true;
    payBtn.innerHTML = `
        <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Se procesează...
    `;

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

    const data = {
        service_type: currentServiceType,
        event_id: document.getElementById('service-event').value,
        payment_method: paymentMethod
    };

    switch (currentServiceType) {
        case 'featuring': {
            const featStartVal = document.getElementById('featuring-start').value;
            const featEndVal = document.getElementById('featuring-end').value;
            const todayStr = new Date().toISOString().slice(0, 10);
            let startDatetime, endDatetime;

            if (featStartVal === todayStr) {
                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                startDatetime = featStartVal + 'T' + hh + ':' + mm;
                endDatetime = featEndVal + 'T' + hh + ':' + mm;
            } else {
                startDatetime = featStartVal + 'T07:00';
                endDatetime = featEndVal + 'T00:00';
            }

            data.config = {
                locations: Array.from(document.querySelectorAll('input[name="featuring_locations[]"]:checked')).map(c => c.value),
                start_date: startDatetime,
                end_date: endDatetime,
            };
            break;
        }
        case 'email': {
            const emailAudienceType = document.querySelector('input[name="email_audience"]:checked').value;
            const emailDate = document.getElementById('email-send-date').value;
            const emailTime = document.getElementById('email-send-time').value || '10:00';
            const aud = emailAudiences[emailAudienceType] || {};
            data.config = {
                audience_type: emailAudienceType,
                template: document.querySelector('input[name="email_template"]:checked')?.value || 'classic',
                send_date: emailDate + 'T' + emailTime,
                recipient_count: aud.filtered_count || 0,
                perfect_count: aud.perfect_count || aud.filtered_count || 0,
                partial_count: aud.partial_count || 0,
                variant_indices: window._emailPreviewVariants || {},
                filters: {
                    age_min: document.getElementById('email-filter-age-min').value || null,
                    age_max: document.getElementById('email-filter-age-max').value || null,
                    gender: document.getElementById('email-filter-gender').value || null,
                    cities: getMultiselectValues('email-filter-city'),
                    categories: getMultiselectValues('email-filter-category'),
                    genres: getMultiselectValues('email-filter-genre')
                }
            };
            break;
        }
        case 'tracking': {
            const platforms = Array.from(document.querySelectorAll('input[name="tracking_platforms[]"]:checked')).map(c => c.value);
            const pixelIds = {};
            platforms.forEach(p => {
                const input = document.querySelector('input[name="tracking_pixel_id_' + p + '"]');
                const val = input ? input.value.trim() : '';
                if (val) pixelIds[p] = val;
            });
            data.config = {
                platforms: platforms,
                duration_months: parseInt(document.getElementById('tracking-duration').value) || 1,
            };
            if (Object.keys(pixelIds).length > 0) {
                data.config.pixel_ids = pixelIds;
            }
            break;
        }
        case 'campaign':
            data.config = {
                campaign_type: document.querySelector('input[name="campaign_type"]:checked').value,
                budget: document.getElementById('campaign-budget').value,
                notes: document.getElementById('campaign-notes').value,
            };
            break;
    }

    try {
        const response = await BileteOnlineAPI.post('/organizer/services/orders', data);

        if (!response.success) {
            throw new Error(response.message || 'Eroare la crearea comenzii');
        }

        const order = response.data.order;
        if (!order) {
            throw new Error('Nu s-a putut crea comanda');
        }

        if (paymentMethod === 'card' && order.total > 0) {
            payBtn.innerHTML = `
                <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Se redirecționează către plată...
            `;

            const eventId = document.getElementById('service-event').value;
            const payResponse = await BileteOnlineAPI.post(`/organizer/services/orders/${order.id}/pay`, {
                return_url: window.location.origin + '/organizator/services/success?order=' + order.id + '&type=' + currentServiceType + '&event=' + eventId,
                cancel_url: window.location.origin + '/organizator/servicii?cancelled=1'
            });

            if (payResponse.success && payResponse.data.payment_url) {
                if (payResponse.data.method === 'POST' && payResponse.data.form_data) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = payResponse.data.payment_url;

                    for (const [key, value] of Object.entries(payResponse.data.form_data)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    window.location.href = payResponse.data.payment_url;
                }
            } else {
                throw new Error(payResponse.message || 'Nu s-a putut iniția plata');
            }
        } else if (paymentMethod === 'transfer') {
            orgNotify('Comanda a fost înregistrată! Vei primi un email cu instrucțiunile de plată prin transfer bancar.', 'success');
            closeServiceModal();
            loadActiveServices();
        } else {
            orgNotify('Serviciul a fost activat cu succes!', 'success');
            closeServiceModal();
            loadActiveServices();
        }
    } catch (error) {
        console.error('Service order error:', error);
        orgNotify(error.message || 'Eroare la procesarea comenzii. Încearcă din nou.', 'error');
        payBtn.disabled = false;
        payBtn.innerHTML = originalBtnText;
    }
}

function viewServiceDetails(id) {
    window.location.href = '/organizator/services/' + id;
}

// Email Preview Functions - Subject & promo text variants per template

const emailSubjectVariants = {
    classic: [
        (n) => `${n} - Nu rata!`,
        (n) => `Ești pregătit? ${n} te așteaptă!`,
        (n) => `Bilete disponibile: ${n}`,
        (n) => `Hai la ${n}! Asigură-ți locul`,
        (n) => `${n} - Activitatea pe care nu vrei să o ratezi`
    ],
    urgent: [
        (n) => `ULTIMELE BILETE pentru ${n}!`,
        (n) => `Stoc limitat! ${n} se vinde rapid`,
        (n) => `Nu rata ${n} - mai sunt puține bilete!`,
        (n) => `Ultimele locuri disponibile la ${n}`,
        (n) => `Grăbește-te cu biletele! ${n} aproape sold out`
    ],
    reminder: [
        (n) => `Reminder: ${n} este în curând!`,
        (n) => `Nu uita! ${n} se apropie`,
        (n) => `Mai sunt câteva zile până la ${n}`,
        (n) => `${n} - încă mai poți obține bilete`,
        (n) => `Pregătește-te pentru ${n}!`
    ]
};

const emailPromoTexts = {
    classic: [
        'Activitatea pe care o așteptai este aproape! Asigură-te că ai bilete pentru a nu rata această experiență unică.',
        'O activitate pe care nu vrei să o ratezi. Rezervă-ți biletele acum și pregătește-te pentru o seară de neuitat!',
        'Vino să trăiești o experiență memorabilă! Biletele sunt disponibile, nu amâna - asigură-ți locul chiar acum.',
        'Ești gata pentru o experiență extraordinară? Biletele se vând repede, așa că nu ezita să îți faci rezervarea.',
        'Fii parte din această activitate specială! Profită de disponibilitate și cumpără biletele cât mai sunt locuri.'
    ],
    urgent: [
        'Biletele se vând rapid! Rezervă-ți locul acum pentru a nu rămâne pe dinafară.',
        'Stocul este aproape epuizat! Nu mai sta pe gânduri - aceasta ar putea fi ultima ta șansă.',
        'Cererea este uriașă și locurile se termină! Acționează acum și nu rata această activitate.',
        'Ultimele bilete se vând chiar acum. Dacă încă nu ți-ai asigurat locul, acum e momentul!',
        'Disponibilitatea scade rapid! Fiecare minut contează - cumpără biletele înainte să fie prea târziu.'
    ],
    reminder: [
        'Pregătește-te pentru o experiență de neuitat! Nu uita să îți rezervi biletele dacă nu ai făcut-o deja.',
        'Activitatea este chiar după colț! Dacă nu ai bilete încă, mai ai șansa să le obții acum.',
        'Marchează-ți în calendar și nu rata! Biletele sunt încă disponibile pentru tine.',
        'Numărătoarea inversă a început! Ai tot ce îți trebuie? Dacă nu, biletele te așteaptă.',
        'Activitatea se apropie cu pași repezi. Asigură-te că ești pregătit - cumpără bilete acum!'
    ]
};

let lockedVariants = {};

function getLockedVariantIndex(templateType, variantType, maxLen) {
    const key = templateType + '_' + variantType;
    if (lockedVariants[key] === undefined) {
        lockedVariants[key] = Math.floor(Math.random() * maxLen);
    }
    return lockedVariants[key];
}

function buildVenueBox(event) {
    const venueName = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || '';
    const venueCity = event.venue_city || (typeof event.venue === 'object' ? event.venue?.city : '') || '';
    if (!venueName) return '';
    return `
        <div class="p-4 mb-6 border-2 border-ink/10 bg-paper-2 rounded-xl">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-ink/10 rounded-lg">
                    <svg class="w-5 h-5 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-ink">${escHtml(venueName)}</p>
                    ${venueCity ? `<p class="text-xs text-ink-soft">${escHtml(venueCity)}</p>` : ''}
                </div>
            </div>
        </div>`;
}

function buildArtistsBox(event) {
    const artists = event.artists || event.artist_names || [];
    if (!artists || artists.length === 0) return '';
    const artistList = Array.isArray(artists) ? artists : [artists];
    return `
        <div class="p-4 mb-6 border-2 border-sky/20 bg-sky/5 rounded-xl">
            <p class="mb-2 text-xs font-bold tracking-wide text-sky uppercase">Artiști</p>
            <div class="flex flex-wrap gap-2">
                ${artistList.map(a => {
                    const name = typeof a === 'object' ? (a.name || a.title || '') : a;
                    return name ? `<span class="inline-block px-3 py-1 text-xs font-medium text-sky bg-sky/10 rounded-full">${escHtml(name)}</span>` : '';
                }).join('')}
            </div>
        </div>`;
}

function buildDescriptionBox(event) {
    const desc = event.short_description || event.description || '';
    if (!desc) return '';
    const truncated = desc.length > 300 ? desc.substring(0, 300) + '...' : desc;
    return `<p class="mb-6 text-sm text-ink-soft">${escHtml(truncated)}</p>`;
}

const emailTemplates = {
    classic: {
        subject: (eventName) => {
            const variants = emailSubjectVariants.classic;
            const idx = getLockedVariantIndex('classic', 'subject', variants.length);
            return variants[idx](eventName);
        },
        body: (event, eventName, eventDate, eventVenue) => {
            const variants = emailPromoTexts.classic;
            const idx = getLockedVariantIndex('classic', 'promo', variants.length);
            return `
            <div class="mb-6 text-center">
                <img src="${getStorageUrl(event.image)}" alt="${escHtml(eventName)}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-ink">${escHtml(eventName)}</h1>
            <div class="p-4 mb-6 bg-paper-2 rounded-xl">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs tracking-wide uppercase text-ink-soft">Data</p>
                        <p class="font-bold text-ink">${eventDate ? BileteOnlineUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide uppercase text-ink-soft">Locație</p>
                        <p class="font-bold text-ink">${escHtml(eventVenue)}</p>
                    </div>
                </div>
            </div>
            ${buildDescriptionBox(event)}
            ${buildVenueBox(event)}
            ${buildArtistsBox(event)}
            <p class="mb-6 text-center text-ink-soft">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-3 font-bold text-paper transition-colors bg-vermilion rounded-xl hover:bg-vermilion-d">Cumpără Bilete Acum</a>
            </div>
            <hr class="my-6 border-ink/10">
            <p class="text-xs text-center text-ink-soft">
                Ai primit acest email pentru că ești abonat la newsletter-ul Bilete Online.<br>
                <a href="#" class="text-vermilion hover:underline">Dezabonare</a>
            </p>`;
        }
    },
    urgent: {
        subject: (eventName) => {
            const variants = emailSubjectVariants.urgent;
            const idx = getLockedVariantIndex('urgent', 'subject', variants.length);
            return variants[idx](eventName);
        },
        body: (event, eventName, eventDate, eventVenue) => {
            const variants = emailPromoTexts.urgent;
            const idx = getLockedVariantIndex('urgent', 'promo', variants.length);
            return `
            <div class="p-4 mb-6 border-2 border-vermilion/20 bg-vermilion/5 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-vermilion rounded-full">
                        <svg class="w-6 h-6 text-paper" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-vermilion">Ultimele bilete disponibile!</p>
                        <p class="text-sm text-vermilion">Nu rata șansa de a fi acolo</p>
                    </div>
                </div>
            </div>
            <div class="mb-6 text-center">
                <img src="${getStorageUrl(event.image)}" alt="${escHtml(eventName)}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-ink">${escHtml(eventName)}</h1>
            <div class="p-4 mb-6 bg-paper-2 rounded-xl">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs tracking-wide uppercase text-ink-soft">Data</p>
                        <p class="font-bold text-ink">${eventDate ? BileteOnlineUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide uppercase text-ink-soft">Locație</p>
                        <p class="font-bold text-ink">${escHtml(eventVenue)}</p>
                    </div>
                </div>
            </div>
            <p class="mb-4 text-center text-ink-soft">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-4 text-lg font-bold text-paper transition-colors bg-vermilion rounded-xl hover:bg-vermilion-d animate-pulse">Cumpără ACUM - Stoc Limitat!</a>
            </div>
            <hr class="my-6 border-ink/10">
            <p class="text-xs text-center text-ink-soft">
                Ai primit acest email pentru că ești abonat la newsletter-ul Bilete Online.<br>
                <a href="#" class="text-vermilion hover:underline">Dezabonare</a>
            </p>`;
        }
    },
    reminder: {
        subject: (eventName) => {
            const variants = emailSubjectVariants.reminder;
            const idx = getLockedVariantIndex('reminder', 'subject', variants.length);
            return variants[idx](eventName);
        },
        body: (event, eventName, eventDate, eventVenue) => {
            const variants = emailPromoTexts.reminder;
            const idx = getLockedVariantIndex('reminder', 'promo', variants.length);
            return `
            <div class="p-4 mb-6 border-2 border-sky/20 bg-sky/5 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-sky rounded-full">
                        <svg class="w-6 h-6 text-paper" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-sky">Activitatea se apropie!</p>
                        <p class="text-sm text-sky">Încă mai poți obține bilete</p>
                    </div>
                </div>
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-ink">${escHtml(eventName)}</h1>
            <div class="mb-6 text-center">
                <img src="${getStorageUrl(event.image)}" alt="${escHtml(eventName)}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <div class="p-6 mb-6 text-center text-paper bg-gradient-to-r from-sky to-vermilion rounded-xl">
                <p class="text-sm tracking-wide uppercase opacity-80">Marchează în calendar</p>
                <p class="my-2 text-3xl font-bold">${eventDate ? BileteOnlineUtils.formatDate(eventDate) : 'TBA'}</p>
                <p class="text-lg">${escHtml(eventVenue)}</p>
            </div>
            ${buildDescriptionBox(event)}
            ${buildVenueBox(event)}
            ${buildArtistsBox(event)}
            <p class="mb-6 text-center text-ink-soft">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-3 font-bold text-paper transition-colors bg-sky rounded-xl hover:opacity-90">Vezi Detalii & Cumpără Bilete</a>
            </div>
            <hr class="my-6 border-ink/10">
            <p class="text-xs text-center text-ink-soft">
                Ai primit acest email pentru că ești abonat la newsletter-ul Bilete Online.<br>
                <a href="#" class="text-vermilion hover:underline">Dezabonare</a>
            </p>`;
        }
    }
};

function showEmailPreview() {
    const eventId = document.getElementById('service-event').value;
    const event = events.find(e => e.id == eventId);
    if (!event) {
        orgNotify('Selectează o activitate pentru a vedea previzualizarea', 'error');
        return;
    }

    const audienceType = document.querySelector('input[name="email_audience"]:checked')?.value || 'own';
    const recipientCount = emailAudiences[audienceType]?.filtered_count || 0;
    const templateType = document.querySelector('input[name="email_template"]:checked')?.value || 'classic';

    const eventName = event.name || event.title || '';
    const eventDate = event.starts_at || event.date;
    const eventVenue = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || 'TBA';

    const template = emailTemplates[templateType];
    const subjectText = template.subject(eventName);

    document.getElementById('preview-recipients').textContent = BileteOnlineUtils.formatNumber(recipientCount) + ' destinatari';
    document.getElementById('preview-subject').textContent = subjectText;

    const previewHtml = template.body(event, eventName, eventDate, eventVenue);
    document.getElementById('email-preview-content').innerHTML = previewHtml;

    window._emailPreviewSubject = subjectText;
    window._emailPreviewVariants = { ...lockedVariants };

    document.getElementById('email-preview-modal').classList.remove('hidden');
    document.getElementById('email-preview-modal').classList.add('flex');
}

function closeEmailPreview() {
    document.getElementById('email-preview-modal').classList.add('hidden');
    document.getElementById('email-preview-modal').classList.remove('flex');
}

// Placement Preview Functions
const placementPreviews = {
    home_hero: {
        title: 'Prima pagină — Hero Banner',
        description: 'Activitatea ta apare ca banner principal pe prima pagină, vizibilă imediat la accesarea site-ului. Poziția cu cea mai mare vizibilitate.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-paper-2 select-none">
                <div class="bg-paper border-b-2 border-ink/10 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-vermilion">bilete</span>
                    <div class="flex items-center gap-1 ml-2 text-ink-soft">
                        <span class="px-2 py-1 rounded cursor-pointer">Concerte</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Festivaluri</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Teatru</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Sport</span>
                    </div>
                    <div class="flex items-center gap-2 ml-auto">
                        <div class="bg-paper-2 rounded-lg px-3 py-1.5 text-ink-soft flex items-center gap-1.5 w-28">
                            <svg class="flex-shrink-0 w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Caută...</span>
                        </div>
                        <button class="bg-vermilion text-paper px-3 py-1.5 rounded-lg font-medium cursor-default">Cont</button>
                    </div>
                </div>
                <div class="relative ring-4 ring-vermilion ring-inset">
                    <img src="${eventImage}" alt="${escHtml(eventName)}" class="object-cover w-full h-56">
                    <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/50 to-transparent"></div>
                    <div class="absolute top-3 right-3 z-30 bg-ochre text-ink text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-wide shadow-lg flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Activitatea ta
                    </div>
                    <div class="absolute inset-0 flex flex-col justify-end p-5">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-[10px] text-ochre font-bold uppercase tracking-widest">★ Promovat</span>
                            <span class="text-[10px] text-paper/90">•</span>
                            <span class="text-[10px] text-paper/90 uppercase tracking-wide">Concert</span>
                        </div>
                        <h2 class="max-w-sm mb-1 text-xl font-black leading-tight text-paper drop-shadow-lg">${escHtml(eventName)}</h2>
                        <p class="flex items-center gap-2 mb-3 text-xs text-paper/70">
                            <span>${escHtml(eventDate)}</span><span>•</span><span>${escHtml(eventVenue)}</span>
                        </p>
                        <div class="flex items-center gap-3">
                            <button class="px-4 py-2 text-xs font-bold text-paper rounded-lg shadow-lg cursor-default bg-vermilion">Cumpără Bilete</button>
                            <span class="text-xs font-bold text-paper/80">de la 80 RON</span>
                        </div>
                    </div>
                    <div class="absolute bottom-3 right-4 flex items-center gap-1.5">
                        <div class="w-5 h-1.5 bg-paper rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-paper/40 rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-paper/40 rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-paper/40 rounded-full"></div>
                    </div>
                </div>
                <div class="flex gap-2 px-4 py-2 overflow-x-auto bg-paper border-b-2 border-ink/10">
                    <span class="px-3 py-1 text-xs font-medium text-paper rounded-full cursor-pointer bg-vermilion whitespace-nowrap">Toate</span>
                    <span class="px-3 py-1 text-xs text-ink-soft bg-paper-2 rounded-full cursor-pointer whitespace-nowrap">Concerte</span>
                    <span class="px-3 py-1 text-xs text-ink-soft bg-paper-2 rounded-full cursor-pointer whitespace-nowrap">Festivaluri</span>
                    <span class="px-3 py-1 text-xs text-ink-soft bg-paper-2 rounded-full cursor-pointer whitespace-nowrap">Teatru</span>
                    <span class="px-3 py-1 text-xs text-ink-soft bg-paper-2 rounded-full cursor-pointer whitespace-nowrap">Sport</span>
                    <span class="px-3 py-1 text-xs text-ink-soft bg-paper-2 rounded-full cursor-pointer whitespace-nowrap">Comedy</span>
                </div>
                <div class="p-4 opacity-40">
                    <p class="mb-3 text-xs font-bold tracking-wider text-ink-soft uppercase">Următoarele activități</p>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="overflow-hidden bg-paper shadow-sm rounded-xl"><img src="https://picsum.photos/seed/bilete-ev1/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper shadow-sm rounded-xl"><img src="https://picsum.photos/seed/bilete-ev2/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper shadow-sm rounded-xl"><img src="https://picsum.photos/seed/bilete-ev3/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper shadow-sm rounded-xl"><img src="https://picsum.photos/seed/bilete-ev4/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    home_recommendations: {
        title: 'Prima pagină — Secțiunea Recomandări',
        description: 'Activitatea ta apare prima în secțiunea "Recomandate pentru tine" de pe prima pagină, vizibilă pentru toți vizitatorii site-ului.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-paper-2 select-none">
                <div class="bg-paper border-b-2 border-ink/10 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-vermilion">bilete</span>
                    <div class="flex items-center gap-1 ml-2 text-ink-soft">
                        <span class="px-2 py-1 rounded cursor-pointer">Concerte</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Festivaluri</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Teatru</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-paper-2 rounded-lg px-3 py-1.5 text-ink-soft flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Caută...</span>
                        </div>
                    </div>
                </div>
                <div class="relative pointer-events-none opacity-30">
                    <img src="https://picsum.photos/seed/bilete-hero2/900/300" class="object-cover w-full h-28">
                    <div class="absolute inset-0 flex flex-col justify-end p-4 bg-gradient-to-r from-ink/70 to-transparent">
                        <div class="w-40 h-3 mb-1 rounded bg-paper/40"></div>
                        <div class="w-24 h-2 rounded bg-paper/30"></div>
                    </div>
                </div>
                <div class="px-4 pt-5 pb-4 bg-paper ring-4 ring-vermilion ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base text-vermilion">⭐</span>
                            <span class="font-bold text-ink">Recomandate pentru tine</span>
                            <span class="bg-ochre/20 text-ochre text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wide">Activitatea ta</span>
                        </div>
                        <span class="text-xs font-medium cursor-pointer text-vermilion">Vezi toate →</span>
                    </div>
                    <div class="flex gap-3 pb-1 overflow-x-auto">
                        <div class="relative flex-shrink-0 w-40 overflow-hidden bg-paper shadow-lg cursor-pointer rounded-xl ring-2 ring-vermilion">
                            <div class="absolute top-1.5 right-1.5 z-10 bg-vermilion text-paper text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${escHtml(eventName)}" class="object-cover w-full h-24">
                            <div class="p-2.5">
                                <p class="mb-1 text-xs font-bold leading-tight text-ink truncate">${escHtml(eventName)}</p>
                                <p class="text-[10px] text-ink-soft mb-0.5">${escHtml(eventDate)}</p>
                                <p class="text-[10px] text-ink-soft truncate mb-1.5">${escHtml(eventVenue)}</p>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-vermilion">80 RON</p>
                                    <button class="text-[10px] bg-vermilion text-paper px-2 py-0.5 rounded-lg cursor-default font-bold">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex-shrink-0 overflow-hidden bg-paper shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/bilete-ev1/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-paper shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/bilete-ev2/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-paper shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/bilete-ev3/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-paper shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/bilete-ev4/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-2 bg-ink/10 rounded"></div></div></div>
                    </div>
                </div>
                <div class="p-4 mt-2 bg-paper opacity-35">
                    <div class="w-40 h-3 mb-3 bg-ink/15 rounded"></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev5/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev6/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev7/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 bg-ink/10 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    category: {
        title: 'Pagina Categorie Activitate',
        description: 'Activitatea ta apare ca activitate recomandată la începutul paginii de categorie (ex: Concerte, Festivaluri). Ajunge la publicul targetat pe tipul de activitate.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-paper-2 select-none">
                <div class="bg-paper border-b-2 border-ink/10 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-vermilion">bilete</span>
                    <div class="flex items-center gap-1 ml-2 text-ink-soft">
                        <span class="cursor-pointer hover:text-vermilion">Acasă</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <span class="font-bold text-ink">Concerte</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-paper-2 rounded-lg px-3 py-1.5 text-ink-soft flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Caută...</span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 bg-paper border-b border-ink/10">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 shadow-sm bg-gradient-to-br from-ochre to-vermilion rounded-xl">
                            <svg class="w-5 h-5 text-paper" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </div>
                        <div>
                            <h1 class="text-base font-black text-ink">Concerte</h1>
                            <p class="text-xs text-ink-soft">247 de activități disponibile</p>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3 overflow-x-auto">
                        <span class="px-3 py-1 bg-ochre text-paper text-[11px] rounded-full font-medium whitespace-nowrap cursor-pointer">Toate concertele</span>
                        <span class="px-3 py-1 bg-paper-2 text-ink-soft text-[11px] rounded-full whitespace-nowrap cursor-pointer">Rock</span>
                        <span class="px-3 py-1 bg-paper-2 text-ink-soft text-[11px] rounded-full whitespace-nowrap cursor-pointer">Pop</span>
                        <span class="px-3 py-1 bg-paper-2 text-ink-soft text-[11px] rounded-full whitespace-nowrap cursor-pointer">Electronic</span>
                        <span class="px-3 py-1 bg-paper-2 text-ink-soft text-[11px] rounded-full whitespace-nowrap cursor-pointer">Jazz</span>
                    </div>
                </div>
                <div class="p-4 bg-paper ring-4 ring-vermilion ring-inset">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] font-black text-vermilion uppercase tracking-wider">★ Activitate Promovată</span>
                        <span class="bg-ochre/20 text-ochre text-[10px] font-black px-1.5 py-0.5 rounded-full">Activitatea ta</span>
                    </div>
                    <div class="relative overflow-hidden bg-paper shadow-lg cursor-pointer rounded-2xl ring-2 ring-vermilion">
                        <div class="absolute top-2.5 right-2.5 z-10 bg-vermilion text-paper text-[9px] font-black px-2 py-0.5 rounded-full shadow-md">★ PROMOVAT</div>
                        <div class="flex">
                            <img src="${eventImage}" alt="${escHtml(eventName)}" class="flex-shrink-0 object-cover w-32 h-28">
                            <div class="flex-1 p-3">
                                <p class="font-black text-ink text-sm mb-0.5 leading-tight">${escHtml(eventName)}</p>
                                <div class="flex items-center gap-1 text-[11px] text-ink-soft mb-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${escHtml(eventDate)}
                                </div>
                                <div class="flex items-center gap-1 text-[11px] text-ink-soft mb-2">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    ${escHtml(eventVenue)}
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] text-ink-soft">de la</p>
                                        <p class="text-sm font-black text-vermilion">80 RON</p>
                                    </div>
                                    <button class="bg-vermilion text-paper text-xs font-bold px-3 py-1.5 rounded-xl cursor-default shadow-sm">Cumpără Bilete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 opacity-35">
                    <p class="mb-3 text-xs font-bold text-ink-soft">Toate concertele</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="overflow-hidden bg-paper shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/bilete-ev1/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-3 rounded bg-vermilion/20"></div></div></div>
                        <div class="overflow-hidden bg-paper shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/bilete-ev2/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-3 rounded bg-vermilion/20"></div></div></div>
                        <div class="overflow-hidden bg-paper shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/bilete-ev3/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-ink/15 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-ink/10 rounded"></div><div class="w-1/2 h-3 rounded bg-vermilion/20"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    city: {
        title: 'Pagina Oraș Activitate',
        description: 'Activitatea ta apare în secțiunea "Populare în [oraș]" pe pagina dedicată orașului activității. Ajunge la utilizatorii care caută activități în zona lor.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-paper-2 select-none">
                <div class="bg-paper border-b-2 border-ink/10 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-vermilion">bilete</span>
                    <div class="flex items-center gap-1 ml-2 text-ink-soft">
                        <span class="cursor-pointer hover:text-vermilion">Acasă</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <span class="font-bold text-ink">București</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-paper-2 rounded-lg px-3 py-1.5 text-ink-soft flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Caută...</span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 text-paper bg-gradient-to-r from-sky to-forest">
                    <div class="flex items-center gap-2 mb-0.5">
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-lg font-black">București</span>
                    </div>
                    <p class="mb-3 text-xs text-paper/80">Descoperă cele mai bune activități din oraș</p>
                    <div class="flex gap-2">
                        <span class="px-2.5 py-1 bg-paper/25 text-paper text-[11px] rounded-full font-medium cursor-pointer">Toate</span>
                        <span class="px-2.5 py-1 bg-paper/10 text-paper/80 text-[11px] rounded-full cursor-pointer">Concerte</span>
                        <span class="px-2.5 py-1 bg-paper/10 text-paper/80 text-[11px] rounded-full cursor-pointer">Weekend</span>
                        <span class="px-2.5 py-1 bg-paper/10 text-paper/80 text-[11px] rounded-full cursor-pointer">Gratuit</span>
                    </div>
                </div>
                <div class="px-4 pt-4 pb-4 bg-paper ring-4 ring-sky ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔥</span>
                            <span class="font-bold text-ink">Populare în București</span>
                            <span class="bg-ochre/20 text-ochre text-[10px] font-black px-1.5 py-0.5 rounded-full">Activitatea ta</span>
                        </div>
                        <span class="text-xs font-medium text-sky cursor-pointer">Toate →</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="relative col-span-2 overflow-hidden bg-paper shadow-lg cursor-pointer rounded-2xl ring-2 ring-sky">
                            <div class="absolute top-2 left-2 z-10 bg-ochre text-ink text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">Activitatea ta</div>
                            <div class="absolute top-2 right-2 z-10 bg-sky text-paper text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${escHtml(eventName)}" class="object-cover w-full h-28">
                            <div class="p-3">
                                <p class="mb-1 text-sm font-black leading-tight text-ink">${escHtml(eventName)}</p>
                                <p class="text-[11px] text-ink-soft mb-0.5">${escHtml(eventDate)}</p>
                                <p class="text-[11px] text-ink-soft truncate mb-2">${escHtml(eventVenue)}</p>
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-black text-sky">de la 80 RON</p>
                                    <button class="bg-sky text-paper text-[11px] font-bold px-3 py-1 rounded-lg cursor-default shadow-sm">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 opacity-40">
                            <div class="overflow-hidden bg-paper shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/bilete-ev1/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2 mb-1 bg-ink/15 rounded"></div><div class="h-1.5 bg-ink/10 rounded w-2/3"></div></div></div>
                            <div class="overflow-hidden bg-paper shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/bilete-ev2/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2 mb-1 bg-ink/15 rounded"></div><div class="h-1.5 bg-ink/10 rounded w-2/3"></div></div></div>
                        </div>
                    </div>
                </div>
                <div class="p-4 mt-2 bg-paper opacity-35">
                    <div class="w-48 h-3 mb-3 bg-ink/15 rounded"></div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev3/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-ink/15 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev4/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-ink/15 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev5/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-ink/15 rounded"></div></div></div>
                        <div class="overflow-hidden bg-paper-2 rounded-xl"><img src="https://picsum.photos/seed/bilete-ev6/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-ink/15 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    }
};

function showPlacementPreview(placement) {
    const preview = placementPreviews[placement];
    if (!preview) return;

    const eventId = document.getElementById('service-event')?.value;
    const event = events.find(e => e.id == eventId);
    const eventName = event ? (event.name || event.title || 'Activitatea Ta') : 'Activitatea Ta';
    const rawImage = event?.image ? getStorageUrl(event.image) : null;
    const eventImage = rawImage || 'https://picsum.photos/seed/bilete-hero/900/450';
    const eventDate = event ? (BileteOnlineUtils.formatDate(event.starts_at || event.date) || 'Data activității') : 'Data activității';
    const eventVenue = event
        ? (event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || 'Locația activității')
        : 'Locația activității';

    document.getElementById('placement-preview-title').textContent = preview.title;
    document.getElementById('placement-preview-description').textContent = preview.description;
    document.getElementById('placement-preview-content').innerHTML = preview.buildContent(eventName, eventImage, eventDate, eventVenue);

    document.getElementById('placement-preview-modal').classList.remove('hidden');
    document.getElementById('placement-preview-modal').classList.add('flex');
}

function closePlacementPreview() {
    document.getElementById('placement-preview-modal').classList.add('hidden');
    document.getElementById('placement-preview-modal').classList.remove('flex');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
