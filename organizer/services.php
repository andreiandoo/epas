<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Servicii Extra';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Success Messages -->
            <div id="success-banner" class="hidden p-4 mb-6 border bg-success/10 border-success/30 rounded-2xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-success rounded-xl">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-success" id="success-title">Succes!</p>
                        <p class="text-sm text-success/80" id="success-message">Operatiunea a fost finalizata cu succes.</p>
                    </div>
                    <button onclick="closeSuccessBanner()" aria-label="Închide" class="p-2 rounded-lg hover:bg-success/10">
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Cancelled Message -->
            <div id="cancelled-banner" class="hidden p-4 mb-6 border bg-amber-50 border-amber-200 rounded-2xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-amber-500 rounded-xl">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-amber-800">Plata Anulata</p>
                        <p class="text-sm text-amber-700">Plata a fost anulata. Poti incerca din nou oricand doresti.</p>
                    </div>
                    <button onclick="closeCancelledBanner()" class="p-2 rounded-lg hover:bg-amber-100">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Servicii Extra</h1>
                    <p class="text-sm text-muted">Promoveaza-ti evenimentele si creste vanzarile</p>
                </div>
                <a href="/organizator/servicii/comenzi" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Comenzile mele
                </a>
            </div>

            <!-- Services Grid -->
            <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2">
                <!-- Event Featuring -->
                <div class="flex flex-col justify-between overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-gradient-to-br from-primary to-primary-dark rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-1 text-lg font-bold text-secondary">Promovare Eveniment</h3>
                                <p class="mb-4 text-sm text-muted">Afiseaza evenimentul tau pe prima pagina, in sectiunea de recomandari, pe pagina categoriei sau a orasului evenimentului.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary">Hero Prima Pagina</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary">Recomandari</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary">Categorie</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary">Oras</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-primary" id="card-featuring-price">40 RON</span> / zi</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-surface border-border">
                        <button onclick="openServiceModal('featuring')" class="w-full btn btn-primary bg-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Cumpara Promovare
                        </button>
                    </div>
                </div>

                <!-- Email Marketing -->
                <div class="flex-col justify-between hidden overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-gradient-to-br from-accent to-orange-600 rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-1 text-lg font-bold text-secondary">Email Marketing</h3>
                                <p class="mb-4 text-sm text-muted">Trimite emailuri targetate catre baza noastra de utilizatori sau doar catre clientii tai anteriori.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-accent/10 text-accent">Baza Completa</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-accent/10 text-accent">Audienta Filtrata</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-accent/10 text-accent">Clientii Tai</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-accent" id="card-email-price">0.40 RON</span> / email</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-surface border-border">
                        <button onclick="openServiceModal('email')" class="w-full btn btn-accent">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Cumpara Campanie Email
                        </button>
                    </div>
                </div>

                <!-- Ad Tracking -->
                <div class="flex flex-col justify-between overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-1 text-lg font-bold text-secondary">Tracking Campanii Ads</h3>
                                <p class="mb-4 text-sm text-muted">Conecteaza campaniile tale Facebook, Google sau TikTok pentru a urmari conversiile si ROI-ul.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 text-xs text-blue-600 bg-blue-100 rounded-full">Facebook Ads</span>
                                    <span class="px-2 py-1 text-xs text-blue-600 bg-blue-100 rounded-full">Google Ads</span>
                                    <span class="px-2 py-1 text-xs text-blue-600 bg-blue-100 rounded-full">TikTok Ads</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-blue-600">99 RON</span> / luna</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-surface border-border">
                        <button onclick="openServiceModal('tracking')" class="w-full text-white bg-blue-600 btn hover:bg-blue-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Activeaza Tracking
                        </button>
                    </div>
                </div>

                <!-- Ad Campaign Creation -->
                <div class="flex-col justify-between hidden overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-1 text-lg font-bold text-secondary">Creare Campanii Ads</h3>
                                <p class="mb-4 text-sm text-muted">Lasa echipa noastra sa creeze si sa gestioneze campanii publicitare profesionale pentru evenimentul tau.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 text-xs text-purple-600 bg-purple-100 rounded-full">Strategie Completa</span>
                                    <span class="px-2 py-1 text-xs text-purple-600 bg-purple-100 rounded-full">Design Creativ</span>
                                    <span class="px-2 py-1 text-xs text-purple-600 bg-purple-100 rounded-full">Management</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-purple-600">499 RON</span> / campanie</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-surface border-border">
                        <button onclick="openServiceModal('campaign')" class="w-full text-white bg-purple-600 btn hover:bg-purple-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Solicita Campanie
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Services -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-between p-6 border-b border-border">
                    <h2 class="text-lg font-bold text-secondary">Servicii Active</h2>
                    <select id="service-filter" class="w-auto input">
                        <option value="">Toate</option>
                        <option value="featuring">Promovare</option>
                        <option value="email">Email Marketing</option>
                        <option value="tracking">Ad Tracking</option>
                        <option value="campaign">Campanii Ads</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Serviciu</th>
                                <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Detalii</th>
                                <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Perioada</th>
                                <th class="px-6 py-4 text-sm font-semibold text-left text-secondary">Status</th>
                                <th class="px-6 py-4 text-sm font-semibold text-right text-secondary">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody id="services-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Service Modal -->
    <div id="service-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black/50">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 flex items-center justify-between p-6 bg-white border-b border-border">
                <h3 id="modal-title" class="text-xl font-bold text-secondary">Configureaza Serviciu</h3>
                <button onclick="closeServiceModal()" class="p-2 rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Step Indicator -->
            <div class="px-6 py-4 border-b border-border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2" id="step-1-indicator">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full bg-primary">1</div>
                        <span class="text-sm font-medium text-secondary">Selecteaza Eveniment</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-border mx-4"></div>
                    <div class="flex items-center gap-2" id="step-2-indicator">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full bg-border text-muted">2</div>
                        <span class="text-sm font-medium text-muted">Configureaza</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-border mx-4"></div>
                    <div class="flex items-center gap-2" id="step-3-indicator">
                        <div class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full bg-border text-muted">3</div>
                        <span class="text-sm font-medium text-muted">Plata</span>
                    </div>
                </div>
            </div>

            <form id="service-form" class="p-6" novalidate>
                <input type="hidden" id="service-type">

                <!-- Step 1: Select Event -->
                <div id="step-1" class="step-content">
                    <label class="label">Selecteaza Evenimentul *</label>
                    <select id="service-event" class="w-full mb-4 input" required>
                        <option value="">Alege un eveniment...</option>
                    </select>
                    <div id="event-preview" class="hidden p-4 mb-4 bg-surface rounded-xl">
                        <div class="flex gap-4">
                            <img id="event-image" src="" alt="" class="object-cover w-20 h-20 rounded-lg">
                            <div>
                                <h4 id="event-title" class="font-semibold text-secondary"></h4>
                                <p id="event-date" class="text-sm text-muted"></p>
                                <p id="event-venue" class="text-sm text-muted"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Configuration (different for each service type) -->
                <div id="step-2" class="hidden step-content">
                    <!-- Featuring Options -->
                    <div id="featuring-options" class="hidden space-y-4">
                        <label class="label">Unde vrei sa apara evenimentul?</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="relative">
                                <label class="block cursor-pointer">
                                    <input type="checkbox" name="featuring_locations[]" value="home_hero" class="sr-only peer">
                                    <div class="p-4 border-2 pr-9 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Prima pagina - Hero</p>
                                        <p class="text-sm text-muted">Vizibilitate maxima, banner principal</p>
                                        <p class="mt-2 text-sm font-semibold text-primary" data-price-key="home_hero">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('home_hero')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block cursor-pointer">
                                    <input type="checkbox" name="featuring_locations[]" value="home_recommendations" class="sr-only peer">
                                    <div class="p-4 border-2 pr-9 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Prima pagina - Recomandari</p>
                                        <p class="text-sm text-muted">Sectiunea de recomandari</p>
                                        <p class="mt-2 text-sm font-semibold text-primary" data-price-key="home_recommendations">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('home_recommendations')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block cursor-pointer">
                                    <input type="checkbox" name="featuring_locations[]" value="category" class="sr-only peer">
                                    <div class="p-4 border-2 pr-9 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Pagina categorie eveniment</p>
                                        <p class="text-sm text-muted">Audienta targetata pe categorie</p>
                                        <p class="mt-2 text-sm font-semibold text-primary" data-price-key="category">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('category')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block cursor-pointer">
                                    <input type="checkbox" name="featuring_locations[]" value="city" class="sr-only peer">
                                    <div class="p-4 border-2 pr-9 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Pagina oras eveniment</p>
                                        <p class="text-sm text-muted">Audienta locala din orasul tau</p>
                                        <p class="mt-2 text-sm font-semibold text-primary" data-price-key="city">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('city')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="label">Data Inceput *</label>
                                <input type="date" id="featuring-start" class="w-full input" required>
                            </div>
                            <div>
                                <label class="label">Data Sfarsit *</label>
                                <input type="date" id="featuring-end" class="w-full input" required>
                            </div>
                        </div>
                    </div>

                    <!-- Email Marketing Options -->
                    <div id="email-options" class="hidden space-y-4">
                        <label class="label">Selecteaza Audienta</label>
                        <div class="space-y-3">
                            <label class="block cursor-pointer">
                                <input type="radio" name="email_audience" value="own" class="sr-only peer" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <p class="font-medium text-secondary">Clientii Tai</p>
                                            <p class="text-sm text-muted">Participantii de la evenimentele tale anterioare</p>
                                            <p class="mt-1 text-xs font-semibold text-accent">0.40 RON / email</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-own-count">0</p>
                                            <p class="text-xs text-muted">clienti</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="block cursor-pointer">
                                <input type="radio" name="email_audience" value="marketplace" class="sr-only peer">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <p class="font-medium text-secondary">Baza de Date Marketplace</p>
                                            <p class="text-sm text-muted">Toti utilizatorii activi din platforma</p>
                                            <p class="mt-1 text-xs font-semibold text-accent">0.50 RON / email</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-marketplace-count">~0</p>
                                            <p class="text-xs text-muted">utilizatori</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Audience Filters (Collapsible) -->
                        <div class="overflow-hidden border border-border rounded-xl">
                            <button type="button" onclick="toggleEmailFilters()" class="flex items-center justify-between w-full p-4 transition-colors hover:bg-surface/50">
                                <div class="flex items-center gap-2">
                                    <svg id="email-filters-chevron" class="w-4 h-4 transition-transform -rotate-90 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    <p class="font-medium text-secondary">Filtreaza Audienta</p>
                                </div>
                                <span type="button" onclick="event.stopPropagation(); resetEmailFilters()" class="text-sm text-primary hover:underline">Reseteaza filtre</span>
                            </button>
                            <div id="email-filters-body" class="hidden p-4 pt-0 space-y-4">

                            <!-- Age Range + Gender -->
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="text-xs label">Varsta minima</label>
                                    <select id="email-filter-age-min" class="w-full text-sm input" onchange="updateEmailAudienceCount()">
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
                                    <label class="text-xs label">Varsta maxima</label>
                                    <select id="email-filter-age-max" class="w-full text-sm input" onchange="updateEmailAudienceCount()">
                                        <option value="">Orice</option>
                                        <option value="25">pana la 25</option>
                                        <option value="30">pana la 30</option>
                                        <option value="35">pana la 35</option>
                                        <option value="40">pana la 40</option>
                                        <option value="50">pana la 50</option>
                                        <option value="65">pana la 65</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs label">Gen</label>
                                    <select id="email-filter-gender" class="w-full text-sm input" onchange="updateEmailAudienceCount()">
                                        <option value="">Orice</option>
                                        <option value="male">Barbati</option>
                                        <option value="female">Femei</option>
                                        <option value="other">Altul</option>
                                    </select>
                                </div>
                            </div>

                            <!-- City -->
                            <div>
                                <label class="text-xs label">Oras</label>
                                <div id="email-filter-city" class="searchable-multiselect" data-placeholder="Cauta oras..."></div>
                            </div>

                            <!-- Event Type (Category) -->
                            <div>
                                <label class="text-xs label">Tip Eveniment</label>
                                <div id="email-filter-category" class="searchable-multiselect" data-placeholder="Cauta categorie..."></div>
                            </div>

                            <!-- Music Genre -->
                            <div>
                                <label class="text-xs label">Gen Muzical</label>
                                <div id="email-filter-genre" class="searchable-multiselect" data-placeholder="Cauta gen muzical..."></div>
                            </div>

                            <!-- Filtered Count -->
                            <div class="flex items-center justify-between p-3 rounded-lg bg-accent/10">
                                <div>
                                    <p class="text-sm font-medium text-secondary">Audienta filtrata</p>
                                    <p class="text-xs text-muted">Pe baza filtrelor selectate</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-bold text-accent" id="audience-filtered-count">0</p>
                                    <p class="text-xs text-muted">destinatari</p>
                                </div>
                            </div>

                            <!-- Filter Breakdowns -->
                            <div id="filter-breakdowns" class="hidden p-3 space-y-2 rounded-lg bg-surface">
                                <p class="mb-1 text-xs font-medium text-secondary">Detalii filtre:</p>
                                <div id="breakdown-city" class="hidden">
                                    <div id="breakdown-city-items" class="space-y-0.5"></div>
                                    <p id="breakdown-without-city" class="mt-1 text-xs text-blue-600"></p>
                                </div>
                                <div id="breakdown-category" class="hidden">
                                    <p id="breakdown-without-category" class="text-xs text-blue-600"></p>
                                </div>
                                <div id="breakdown-genre" class="hidden">
                                    <p id="breakdown-without-genre" class="text-xs text-blue-600"></p>
                                </div>
                                <div id="breakdown-birthdate" class="hidden">
                                    <p id="breakdown-birthdate-text" class="text-xs text-amber-600"></p>
                                </div>
                                <!-- Include partial matches toggle -->
                                <div id="partial-matches-toggle" class="hidden pt-2 mt-2 border-t border-border">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="include-partial-matches" class="w-4 h-4 accent-accent" onchange="updateEmailAudienceCount()">
                                        <span class="text-xs text-secondary">Include si utilizatorii care se potrivesc partial (<span id="partial-matches-count">0</span> extra)</span>
                                    </label>
                                    <p class="text-[10px] text-muted mt-1 ml-6">Adauga utilizatorii care corespund celorlalte filtre, dar nu au date pentru filtrele care nu se potrivesc</p>
                                </div>
                            </div>
                            </div><!-- /email-filters-body -->
                        </div>

                        <!-- Cost Summary -->
                        <div class="p-4 bg-surface rounded-xl">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-muted">Tip audienta:</span>
                                <span class="text-sm font-semibold text-secondary" id="email-audience-type-label">Clientii Tai</span>
                            </div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-muted">Cost per email:</span>
                                <span class="font-semibold text-secondary" id="email-price-per">0.40 RON</span>
                            </div>
                            <!-- Simple recipient count (shown when no partial) -->
                            <div class="flex items-center justify-between mb-2" id="email-recipient-row-simple">
                                <span class="text-sm text-muted">Nr. destinatari:</span>
                                <span class="font-semibold text-secondary" id="email-recipient-count">0</span>
                            </div>
                            <!-- Detailed breakdown (shown when partial matches included) -->
                            <div id="email-pricing-breakdown" class="hidden">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-muted">Perfect match:</span>
                                    <span class="text-sm text-secondary"><span id="email-perfect-count">0</span> × <span id="email-perfect-price">0.40</span> = <span class="font-semibold" id="email-perfect-cost">0</span> RON</span>
                                </div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-muted">Partial match <span class="text-xs">(½ pret)</span>:</span>
                                    <span class="text-sm text-secondary"><span id="email-partial-count">0</span> × <span id="email-partial-price">0.20</span> = <span class="font-semibold" id="email-partial-cost">0</span> RON</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2 mt-2 border-t border-border">
                                <span class="text-sm font-medium text-secondary">Cost total estimat:</span>
                                <span class="text-lg font-bold text-accent" id="email-cost-estimate">0 RON</span>
                            </div>
                        </div>

                        <div class="p-4 bg-blue-50 rounded-xl">
                            <div class="flex gap-3">
                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-800">Confidentialitate garantata</p>
                                    <p class="mt-1 text-xs text-blue-700">Nu vei avea acces la datele personale ale utilizatorilor (nume, email, telefon). Emailurile sunt trimise direct prin platforma noastra.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Data Trimitere *</label>
                                <input type="date" id="email-send-date" class="w-full input" required>
                            </div>
                            <div>
                                <label class="label">Ora Trimitere *</label>
                                <input type="time" id="email-send-time" class="w-full input" value="10:00" required>
                            </div>
                        </div>
                        <p class="text-xs text-muted">Programeaza trimiterea pentru momentul optim (recomandat: 10:00 sau 18:00)</p>

                        <!-- Email Template Selection -->
                        <div class="pt-4 mt-4 border-t border-border">
                            <label class="label">Alege Model Email</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="classic" class="sr-only peer" checked>
                                    <div class="p-3 text-center border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                        <div class="flex items-center justify-center w-full h-16 mb-2 rounded-lg bg-gradient-to-b from-accent/20 to-accent/5">
                                            <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <p class="text-xs font-medium text-secondary">Clasic</p>
                                        <p class="text-[10px] text-muted">Imagine + info</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="urgent" class="sr-only peer">
                                    <div class="p-3 text-center border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                        <div class="flex items-center justify-center w-full h-16 mb-2 rounded-lg bg-gradient-to-b from-red-500/20 to-red-500/5">
                                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <p class="text-xs font-medium text-secondary">Urgent</p>
                                        <p class="text-[10px] text-muted">Ultimele bilete</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="reminder" class="sr-only peer">
                                    <div class="p-3 text-center border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                        <div class="flex items-center justify-center w-full h-16 mb-2 rounded-lg bg-gradient-to-b from-blue-500/20 to-blue-500/5">
                                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        </div>
                                        <p class="text-xs font-medium text-secondary">Reminder</p>
                                        <p class="text-[10px] text-muted">Eveniment aproape</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Email Preview Button -->
                        <div class="pt-4">
                            <button type="button" onclick="showEmailPreview()" class="w-full btn btn-secondary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Previzualizeaza Emailul
                            </button>
                            <p class="mt-1 text-xs text-center text-muted">Vezi cum va arata emailul inainte de a-l trimite</p>
                        </div>
                    </div>

                    <!-- Ad Tracking Options -->
                    <div id="tracking-options" class="hidden space-y-4">
                        <label class="label">Platforme de Tracking</label>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border cursor-pointer border-border rounded-xl hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="facebook" class="w-5 h-5 text-blue-600 rounded">
                                <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-secondary">Facebook Pixel</p>
                                    <p class="text-sm text-muted">Track conversii si retargeting</p>
                                </div>
                                <span class="text-sm font-semibold text-blue-600">49 RON / luna</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 border cursor-pointer border-border rounded-xl hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="google" class="w-5 h-5 text-blue-600 rounded">
                                <div class="flex items-center justify-center w-10 h-10 bg-red-100 rounded-lg">
                                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-secondary">Google Ads</p>
                                    <p class="text-sm text-muted">Conversion tracking complet</p>
                                </div>
                                <span class="text-sm font-semibold text-blue-600">49 RON / luna</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 border cursor-pointer border-border rounded-xl hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="tiktok" class="w-5 h-5 text-blue-600 rounded">
                                <div class="flex items-center justify-center w-10 h-10 bg-gray-900 rounded-lg">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-secondary">TikTok Pixel</p>
                                    <p class="text-sm text-muted">Audienta tanara targetata</p>
                                </div>
                                <span class="text-sm font-semibold text-blue-600">49 RON / luna</span>
                            </label>
                        </div>
                        <div>
                            <label class="label">Durata Abonament</label>
                            <select id="tracking-duration" class="w-full input">
                                <option value="1">1 luna</option>
                                <option value="3" selected>3 luni (-10%)</option>
                                <option value="6">6 luni (-15%)</option>
                                <option value="12">12 luni (-25%)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Ad Campaign Creation Options -->
                    <div id="campaign-options" class="hidden space-y-4">
                        <label class="label">Tip Campanie</label>
                        <div class="space-y-3">
                            <label class="block cursor-pointer">
                                <input type="radio" name="campaign_type" value="basic" class="sr-only peer" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Basic</p>
                                            <ul class="mt-2 space-y-1 text-sm text-muted">
                                                <li>• 1 platforma (FB, Google sau TikTok)</li>
                                                <li>• Design creativ inclus</li>
                                                <li>• Setup & optimizare</li>
                                                <li>• Raport final</li>
                                            </ul>
                                        </div>
                                        <p class="text-lg font-bold text-purple-600">499 RON</p>
                                    </div>
                                </div>
                            </label>
                            <label class="block cursor-pointer">
                                <input type="radio" name="campaign_type" value="standard" class="sr-only peer">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Standard</p>
                                            <ul class="mt-2 space-y-1 text-sm text-muted">
                                                <li>• 2 platforme la alegere</li>
                                                <li>• 3 variante creative</li>
                                                <li>• A/B testing</li>
                                                <li>• Optimizare continua</li>
                                                <li>• Rapoarte saptamanale</li>
                                            </ul>
                                        </div>
                                        <p class="text-lg font-bold text-purple-600">899 RON</p>
                                    </div>
                                </div>
                            </label>
                            <label class="block cursor-pointer">
                                <input type="radio" name="campaign_type" value="premium" class="sr-only peer">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Premium</p>
                                            <ul class="mt-2 space-y-1 text-sm text-muted">
                                                <li>• Toate cele 3 platforme</li>
                                                <li>• Design video inclus</li>
                                                <li>• Retargeting avansat</li>
                                                <li>• Manager dedicat</li>
                                                <li>• Rapoarte zilnice</li>
                                            </ul>
                                        </div>
                                        <p class="text-lg font-bold text-purple-600">1,499 RON</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div>
                            <label class="label">Buget Publicitar (RON)</label>
                            <input type="number" id="campaign-budget" class="w-full input" min="500" value="1000" placeholder="Minim 500 RON">
                            <p class="mt-1 text-sm text-muted">Acesta este bugetul pentru platirea reclamelor (separat de costul serviciului)</p>
                        </div>
                        <div>
                            <label class="label">Detalii Suplimentare</label>
                            <textarea id="campaign-notes" class="w-full h-24 input" placeholder="Spune-ne mai multe despre obiectivele tale..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment -->
                <div id="step-3" class="hidden step-content">
                    <div class="p-6 mb-6 bg-surface rounded-xl">
                        <h4 class="mb-4 font-semibold text-secondary">Sumar Comanda</h4>
                        <div id="order-summary" class="space-y-3">
                            <!-- Summary will be populated dynamically -->
                        </div>
                        <div class="pt-4 mt-4 border-t border-border">
                            <div class="flex items-center justify-between text-lg">
                                <span class="font-semibold text-secondary">Total de plata:</span>
                                <span class="font-bold text-primary" id="order-total">0 RON</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="label">Metoda de plata</label>
                        <div class="space-y-3">
                            <label class="block cursor-pointer">
                                <input type="radio" name="payment_method" value="card" class="sr-only peer" checked>
                                <div class="flex items-center gap-4 p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <div class="flex items-center justify-center flex-shrink-0 w-16 h-10 rounded-lg bg-gradient-to-r from-green-600 to-green-700">
                                        <span class="text-white text-[10px] font-bold">NETOPIA</span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-secondary">Card Bancar</p>
                                        <p class="text-xs text-muted">Visa, Mastercard, Maestro</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <div class="flex items-center justify-center w-8 h-5 bg-blue-600 rounded">
                                            <span class="text-white text-[8px] font-bold">VISA</span>
                                        </div>
                                        <div class="flex items-center justify-center w-8 h-5 bg-red-500 rounded">
                                            <span class="text-white text-[6px] font-bold">MC</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="block cursor-pointer">
                                <input type="radio" name="payment_method" value="transfer" class="sr-only peer">
                                <div class="flex items-center gap-4 p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <div class="flex items-center justify-center flex-shrink-0 w-16 h-10 bg-blue-100 rounded-lg">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-secondary">Transfer Bancar</p>
                                        <p class="text-xs text-muted">Activare in 1-2 zile lucratoare</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div id="card-payment-fields" class="p-4 bg-green-50 rounded-xl">
                            <p class="text-sm text-green-800">Vei fi redirectionat catre Netopia Payments pentru a finaliza tranzactia in siguranta.</p>
                        </div>
                        <div id="transfer-payment-info" class="hidden p-4 bg-blue-50 rounded-xl">
                            <p class="mb-2 text-sm font-medium text-blue-800">Detalii pentru transfer bancar:</p>
                            <p class="text-sm text-blue-700">IBAN: RO49 AAAA 1B31 0075 9384 0000</p>
                            <p class="text-sm text-blue-700">Banca: BCR</p>
                            <p class="text-sm text-blue-700">Beneficiar: Ambilet SRL</p>
                            <p class="mt-2 text-sm text-blue-600">Serviciul va fi activat dupa confirmarea platii (1-2 zile lucratoare).</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex gap-3 pt-6 mt-6 border-t border-border">
                    <button type="button" id="btn-back" onclick="prevStep()" class="flex-1 hidden btn btn-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Inapoi
                    </button>
                    <button type="button" id="btn-next" onclick="nextStep()" class="flex-1 btn btn-primary bg-primary">
                        Continua
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <button type="submit" id="btn-pay" class="flex-1 hidden btn btn-primary bg-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Plateste Acum
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Preview Modal -->
    <div id="email-preview-modal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 z-10 flex items-center justify-between p-6 bg-white border-b border-border">
                <h3 class="text-xl font-bold text-secondary">Previzualizare Email</h3>
                <button onclick="closeEmailPreview()" class="p-2 rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="p-4 mb-4 bg-gray-100 rounded-xl">
                    <p class="text-sm text-muted">Aceasta este o previzualizare a emailului care va fi trimis. Continutul final poate varia usor in functie de datele evenimentului.</p>
                </div>

                <!-- Email Preview Container -->
                <div class="overflow-hidden border border-border rounded-xl">
                    <!-- Email Header -->
                    <div class="p-4 border-b bg-surface border-border">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary">
                                <span class="text-sm font-bold text-white">A</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-secondary" id="preview-sender-name">Ambilet</p>
                                <p class="text-xs text-muted" id="preview-sender-email">noreply@ambilet.ro</p>
                            </div>
                        </div>
                        <p class="text-sm"><span class="text-muted">Catre:</span> <span class="text-secondary" id="preview-recipients">1,250 destinatari</span></p>
                        <p class="mt-1 text-sm"><span class="text-muted">Subiect:</span> <span class="font-medium text-secondary" id="preview-subject">🎵 Nu rata evenimentul!</span></p>
                    </div>

                    <!-- Email Body -->
                    <div class="p-6 bg-white">
                        <div id="email-preview-content" class="space-y-4">
                            <!-- Preview will be rendered here -->
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button onclick="closeEmailPreview()" class="flex-1 btn btn-secondary">Inchide</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Placement Preview Modal -->
    <div id="placement-preview-modal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 z-10 flex items-center justify-between p-6 bg-white border-b border-border">
                <h3 class="text-xl font-bold text-secondary" id="placement-preview-title">Previzualizare Plasament</h3>
                <button onclick="closePlacementPreview()" class="p-2 rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="p-4 mb-6 bg-blue-50 rounded-xl">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800" id="placement-preview-description">Descriere plasament</p>
                        </div>
                    </div>
                </div>

                <!-- Preview Container -->
                <div id="placement-preview-content" class="overflow-hidden border border-border rounded-xl">
                    <!-- Preview will be rendered here -->
                </div>

                <div class="flex gap-3 mt-6">
                    <button onclick="closePlacementPreview()" class="flex-1 btn btn-secondary">Inchide</button>
                </div>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() {
    AmbiletAuth.requireOrganizerAuth();
});

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

    // Selected tags area
    const tagsArea = document.createElement('div');
    tagsArea.className = 'flex flex-wrap gap-1 mb-1';
    tagsArea.id = containerId + '-tags';

    // Search input
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'input w-full text-sm';
    searchInput.placeholder = container.dataset.placeholder || 'Cauta...';

    // Dropdown list
    const dropdown = document.createElement('div');
    dropdown.className = 'hidden absolute z-50 w-full mt-1 bg-white border border-border rounded-lg shadow-lg max-h-48 overflow-y-auto';
    dropdown.id = containerId + '-dropdown';

    // Wrapper for relative positioning
    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    wrapper.appendChild(searchInput);
    wrapper.appendChild(dropdown);

    container.appendChild(tagsArea);
    container.appendChild(wrapper);

    // Populate dropdown
    function renderDropdown(filter = '') {
        dropdown.innerHTML = '';
        const lowerFilter = filter.toLowerCase();
        let hasResults = false;
        options.forEach(opt => {
            if (lowerFilter && !opt.label.toLowerCase().includes(lowerFilter)) return;
            hasResults = true;
            const item = document.createElement('div');
            item.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-accent/10 flex items-center gap-2 ' +
                (instance.selected.has(opt.value) ? 'bg-accent/5 text-accent font-medium' : 'text-secondary');
            const check = instance.selected.has(opt.value) ? '<svg class="flex-shrink-0 w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : '<span class="flex-shrink-0 w-4 h-4"></span>';
            item.innerHTML = check + '<span>' + opt.label.trim() + '</span>';
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
            empty.className = 'px-3 py-2 text-sm text-muted';
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
            tag.className = 'inline-flex items-center gap-1 px-2 py-0.5 bg-accent/10 text-accent text-xs rounded-md';
            tag.innerHTML = '<span>' + opt.label.trim() + '</span><button type="button" class="hover:text-red-500">&times;</button>';
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

    // Events
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
    loadPricing();
    loadEvents();
    loadActiveServices();
    loadStats();
    loadEmailFilterOptions();
    setupPaymentMethodToggle();
    setupEmailAudienceToggle();
    setupDateValidation();
    checkUrlParams();
});

function checkUrlParams() {
    const params = new URLSearchParams(window.location.search);

    // Check for success messages
    if (params.get('featuring_activated') === '1') {
        showSuccessBanner('Promovare Activata!', 'Evenimentul tau este acum afisat in sectiunile selectate.');
    } else if (params.get('payment_success') === '1' || params.get('payment') === 'success') {
        showSuccessBanner('Plata Confirmata!', 'Serviciul a fost activat cu succes. Evenimentul tau va aparea in sectiunile selectate.');
    }

    // Check for cancelled payment
    if (params.get('cancelled') === '1' || params.get('payment') === 'cancel') {
        document.getElementById('cancelled-banner').classList.remove('hidden');
    }

    // Clean URL parameters without refresh
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
        const response = await AmbiletAPI.get('/organizer/services/pricing');
        if (response.success && response.data?.pricing) {
            servicePricing = response.data.pricing;
            updatePricingUI();
        } else if (response.success && response.data) {
            // Fallback: if pricing is directly in data
            servicePricing = response.data;
            updatePricingUI();
        }
    } catch (e) {
        console.log('Using default pricing');
        updatePricingUI();
    }
}

function updatePricingUI() {
    // Update email card price
    const cardEmailPrice = document.getElementById('card-email-price');
    if (cardEmailPrice) {
        const lowestEmailPrice = Math.min(
            servicePricing.email.own_per_email || 0.40,
            servicePricing.email.marketplace_per_email || 0.50
        );
        cardEmailPrice.textContent = AmbiletUtils.formatCurrency(lowestEmailPrice);
    }

    // Update email prices in the UI
    const ownPriceEl = document.querySelector('#email-options input[value="own"]')?.closest('label')?.querySelector('.text-accent.font-semibold');
    if (ownPriceEl) {
        ownPriceEl.textContent = AmbiletUtils.formatCurrency(servicePricing.email.own_per_email || 0.40) + ' / email';
    }
    const marketplacePriceEl = document.querySelector('#email-options input[value="marketplace"]')?.closest('label')?.querySelector('.text-accent.font-semibold');
    if (marketplacePriceEl) {
        marketplacePriceEl.textContent = AmbiletUtils.formatCurrency(servicePricing.email.marketplace_per_email || 0.50) + ' / email';
    }

    // Update featuring prices in the UI
    const fp = servicePricing.featuring || {};
    document.querySelectorAll('#featuring-options input[name="featuring_locations[]"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('[data-price-key]');
        if (priceEl) {
            const key = priceEl.getAttribute('data-price-key');
            const price = fp[key] ?? fp[input.value];
            priceEl.textContent = price != null ? price + ' RON / zi' : '— RON / zi';
        }
    });

    // Update "de la" price on featuring card
    const cardFeaturingPrice = document.getElementById('card-featuring-price');
    if (cardFeaturingPrice) {
        const lowestFeaturing = Math.min(...Object.values(fp).filter(v => typeof v === 'number' && v > 0));
        if (isFinite(lowestFeaturing)) {
            cardFeaturingPrice.textContent = lowestFeaturing + ' RON';
        }
    }

    // Update tracking prices
    document.querySelectorAll('#tracking-options input[name="tracking_platforms[]"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('.text-blue-600');
        if (priceEl && servicePricing.tracking.per_platform_monthly) {
            priceEl.textContent = servicePricing.tracking.per_platform_monthly + ' RON / luna';
        }
    });

    // Update campaign prices
    const campaignPrices = servicePricing.campaign;
    document.querySelectorAll('#campaign-options input[name="campaign_type"]').forEach(input => {
        const priceEl = input.closest('label').querySelector('.text-purple-600');
        if (priceEl && campaignPrices[input.value]) {
            priceEl.textContent = AmbiletUtils.formatCurrency(campaignPrices[input.value]);
        }
    });

    // Update the email price per label
    updateEmailAudienceUI();
}

function setupDateValidation() {
    const startInput = document.getElementById('featuring-start');
    const endInput = document.getElementById('featuring-end');

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startInput.min = today;
    endInput.min = today;

    // When start date changes, update end date minimum
    startInput.addEventListener('change', function() {
        if (this.value) {
            endInput.min = this.value;
            // If end date is before start date, reset it
            if (endInput.value && endInput.value < this.value) {
                endInput.value = this.value;
            }
        }
    });

    // Validate end date is not before start date
    endInput.addEventListener('change', function() {
        if (startInput.value && this.value < startInput.value) {
            AmbiletNotifications.error('Data de sfarsit trebuie sa fie dupa data de inceput');
            this.value = startInput.value;
        }
    });
}

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events');
        // API returns paginated response with data array directly
        if (response.success && response.data) {
            const allEvents = Array.isArray(response.data) ? response.data : [];
            // Filter out past/finished events - only show live events for promotions
            events = allEvents.filter(e => e.is_editable !== false && e.is_past !== true && !e.is_cancelled);

            const select = document.getElementById('service-event');

            if (events.length === 0) {
                // No live events available
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Momentan nu ai evenimente in derulare pentru care sa faci promovare';
                opt.disabled = true;
                select.appendChild(opt);
                return;
            }

            events.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                // API returns 'name' not 'title', 'starts_at' not 'date', 'venue_name' not 'venue'
                opt.textContent = e.name || e.title;
                opt.dataset.image = e.image;
                opt.dataset.date = e.starts_at || e.date;
                // Handle venue - can be string, object with name, or null
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
        const response = await AmbiletAPI.get('/organizer/services/orders');
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
        const response = await AmbiletAPI.get('/organizer/services/stats');
        if (response.success) {
            document.getElementById('active-services').textContent = response.data.active_count || 0;
            document.getElementById('total-views').textContent = AmbiletUtils.formatNumber(response.data.total_views || 0);
            document.getElementById('emails-sent').textContent = AmbiletUtils.formatNumber(response.data.emails_sent || 0);
            document.getElementById('total-spent').textContent = AmbiletUtils.formatCurrency(response.data.total_spent || 0);
        }
    } catch (e) {
        // Stats will load when API is available
    }
}

function renderActiveServices() {
    const container = document.getElementById('services-list');
    if (!activeServices.length) {
        container.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-muted">Nu ai servicii active</td></tr>';
        return;
    }

    const typeLabels = {
        featuring: 'Promovare',
        email: 'Email Marketing',
        tracking: 'Ad Tracking',
        campaign: 'Campanie Ads'
    };

    const typeColors = {
        featuring: 'primary',
        email: 'accent',
        tracking: 'blue-600',
        campaign: 'purple-600'
    };

    const statusMap = {
        active: { label: 'Activ', color: 'success' },
        pending_payment: { label: 'Asteapta plata', color: 'warning' },
        pending: { label: 'In procesare', color: 'warning' },
        completed: { label: 'Finalizat', color: 'muted' },
        cancelled: { label: 'Anulat', color: 'muted' },
    };

    container.innerHTML = activeServices.map(s => {
        const statusInfo = statusMap[s.status] || { label: s.status, color: 'muted' };
        return `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <span class="px-3 py-1 bg-${typeColors[s.type] || 'muted'}/10 text-${typeColors[s.type] || 'muted'} text-sm font-medium rounded-full">${typeLabels[s.type] || s.type}</span>
            </td>
            <td class="px-6 py-4 font-medium text-secondary">${s.event_name || '-'}</td>
            <td class="px-6 py-4 text-sm text-muted">${s.details || '-'}</td>
            <td class="px-6 py-4 text-sm text-muted">${AmbiletUtils.formatDate(s.service_start_date)} - ${AmbiletUtils.formatDate(s.service_end_date)}</td>
            <td class="px-6 py-4">
                <span class="px-3 py-1 bg-${statusInfo.color}/10 text-${statusInfo.color} text-sm rounded-full">
                    ${statusInfo.label}
                </span>
            </td>
            <td class="px-6 py-4 text-right">
                <button onclick="viewServiceDetails('${s.id}')" class="p-2 text-muted hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
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

    // Set modal title
    const titles = {
        featuring: 'Promovare Eveniment',
        email: 'Campanie Email Marketing',
        tracking: 'Tracking Campanii Ads',
        campaign: 'Creare Campanie Ads'
    };
    document.getElementById('modal-title').textContent = titles[type];

    // Reset form and steps
    document.getElementById('service-form').reset();
    updateStepUI();

    // Show modal
    document.getElementById('service-modal').classList.remove('hidden');
    document.getElementById('service-modal').classList.add('flex');
}

function closeServiceModal() {
    document.getElementById('service-modal').classList.add('hidden');
    document.getElementById('service-modal').classList.remove('flex');
}

function updateStepUI() {
    // Update step indicators
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step-${i}-indicator`);
        const circle = indicator.querySelector('div');
        const text = indicator.querySelector('span');

        if (i < currentStep) {
            circle.className = 'w-8 h-8 rounded-full bg-success text-white flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-medium text-success';
        } else if (i === currentStep) {
            circle.className = 'w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-medium text-secondary';
        } else {
            circle.className = 'w-8 h-8 rounded-full bg-border text-muted flex items-center justify-center text-sm font-bold';
            text.className = 'text-sm font-medium text-muted';
        }
    }

    // Show/hide step content
    document.querySelectorAll('.step-content').forEach((el, i) => {
        el.classList.toggle('hidden', i + 1 !== currentStep);
    });

    // Show/hide service-specific options in step 2
    if (currentStep === 2) {
        document.getElementById('featuring-options').classList.toggle('hidden', currentServiceType !== 'featuring');
        document.getElementById('email-options').classList.toggle('hidden', currentServiceType !== 'email');
        document.getElementById('tracking-options').classList.toggle('hidden', currentServiceType !== 'tracking');
        document.getElementById('campaign-options').classList.toggle('hidden', currentServiceType !== 'campaign');
    }

    // Update buttons
    document.getElementById('btn-back').classList.toggle('hidden', currentStep === 1);
    document.getElementById('btn-next').classList.toggle('hidden', currentStep === 3);
    document.getElementById('btn-pay').classList.toggle('hidden', currentStep !== 3);
}

function nextStep() {
    if (currentStep === 1) {
        const eventId = document.getElementById('service-event').value;
        if (!eventId) {
            AmbiletNotifications.error('Selecteaza un eveniment');
            return;
        }
        // Show event preview
        const event = events.find(e => e.id == eventId);
        if (event) {
            document.getElementById('event-preview').classList.remove('hidden');
            document.getElementById('event-image').src = getStorageUrl(event.image);
            document.getElementById('event-title').textContent = event.name || event.title || '';
            const eventDate = event.starts_at || event.date;
            document.getElementById('event-date').textContent = eventDate ? AmbiletUtils.formatDate(eventDate) : '';
            document.getElementById('event-venue').textContent = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || '';
        }
    }

    if (currentStep === 2) {
        // Validate step 2 based on service type
        if (!validateStep2()) return;
        // Calculate and show order summary
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
                AmbiletNotifications.error('Selecteaza cel putin o locatie');
                return false;
            }
            // Validate dates
            const startDate = document.getElementById('featuring-start').value;
            const endDate = document.getElementById('featuring-end').value;
            if (!startDate || !endDate) {
                AmbiletNotifications.error('Selecteaza perioada de promovare');
                return false;
            }
            const today = new Date().toISOString().split('T')[0];
            if (startDate < today) {
                AmbiletNotifications.error('Data de inceput nu poate fi in trecut');
                return false;
            }
            if (endDate <= startDate) {
                AmbiletNotifications.error('Data de sfarsit trebuie sa fie dupa data de inceput');
                return false;
            }
            break;
        case 'email':
            // Validate recipient count
            const emailAudienceTypeVal = document.querySelector('input[name="email_audience"]:checked').value;
            const recipientCount = emailAudiences[emailAudienceTypeVal]?.filtered_count || 0;
            if (recipientCount < 1) {
                AmbiletNotifications.error('Nu exista destinatari pentru filtrele selectate');
                return false;
            }
            // Validate send date and time
            const sendDate = document.getElementById('email-send-date').value;
            const sendTime = document.getElementById('email-send-time').value;
            if (!sendDate) {
                AmbiletNotifications.error('Selecteaza data trimiterii');
                return false;
            }
            if (!sendTime) {
                AmbiletNotifications.error('Selecteaza ora trimiterii');
                return false;
            }
            break;
        case 'tracking':
            const platforms = document.querySelectorAll('input[name="tracking_platforms[]"]:checked');
            if (!platforms.length) {
                AmbiletNotifications.error('Selecteaza cel putin o platforma');
                return false;
            }
            break;
        case 'campaign':
            // Validate budget
            const budget = parseInt(document.getElementById('campaign-budget').value);
            if (isNaN(budget) || budget < 500) {
                AmbiletNotifications.error('Bugetul minim este 500 RON');
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

            // Day-based calculation:
            // Days = difference between end date and start date (integer)
            const startDate = new Date(startVal + 'T00:00:00');
            const endDate = new Date(endVal + 'T00:00:00');
            const daysMultiplier = Math.max(Math.round((endDate - startDate) / (1000 * 60 * 60 * 24)), 1);
            const daysDisplay = daysMultiplier;

            const prices = servicePricing.featuring;
            const labels = {
                home_hero: 'Prima pagina - Hero',
                home_recommendations: 'Prima pagina - Recomandari',
                category: 'Pagina categorie eveniment',
                city: 'Pagina oras eveniment'
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
            const emailAudienceLabels = { own: 'Clientii Tai', marketplace: 'Baza Marketplace' };
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
                name: AmbiletUtils.formatNumber(emailCount) + ' destinatari x ' + AmbiletUtils.formatCurrency(emailPricePerUnit),
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
    // Handle category - can be string, object with name, or null
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
            <span class="text-muted">Eveniment:</span>
            <span class="font-medium text-secondary">${eventName}</span>
        </div>
        ${eventCategory ? `
        <div class="flex justify-between text-sm">
            <span class="text-muted">Categorie:</span>
            <span class="font-medium text-secondary">${eventCategory}</span>
        </div>
        ` : ''}
        ${items.map(item => `
            <div class="flex justify-between text-sm">
                <span class="text-muted">${item.name}</span>
                <span class="font-medium text-secondary">${AmbiletUtils.formatCurrency(item.price)}</span>
            </div>
        `).join('')}
    `;

    document.getElementById('order-total').textContent = AmbiletUtils.formatCurrency(total);
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
        // Load cities (request all with per_page=200)
        const citiesRes = await AmbiletAPI.get('/cities', { per_page: 200 });
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
        // Load categories (use ID as value for DB filtering)
        const catRes = await AmbiletAPI.get('/event-categories');
        const categories = catRes?.categories || catRes?.data?.categories || catRes?.data || [];
        if (Array.isArray(categories) && categories.length > 0) {
            emailFilterOptions.categories = categories;
            // Flatten children into parent list
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
        // Load genres (use ID as value for DB filtering)
        const genreRes = await AmbiletAPI.get('/event-genres');
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

    // Load initial audience counts for BOTH types
    updateEmailAudienceCount();
    loadInitialAudienceCounts();
}

async function loadInitialAudienceCounts() {
    // Load base counts for both audience types (no filters)
    try {
        const [ownRes, mpRes] = await Promise.all([
            AmbiletAPI.get('/organizer/services/email-audiences', { audience_type: 'own' }),
            AmbiletAPI.get('/organizer/services/email-audiences', { audience_type: 'marketplace' })
        ]);
        if (ownRes?.success && ownRes.data) {
            emailAudiences.own.count = ownRes.data.total_count || 0;
            document.getElementById('audience-own-count').textContent = AmbiletUtils.formatNumber(ownRes.data.total_count || 0);
        }
        if (mpRes?.success && mpRes.data) {
            emailAudiences.marketplace.count = mpRes.data.total_count || 0;
            document.getElementById('audience-marketplace-count').textContent = '~' + AmbiletUtils.formatNumber(mpRes.data.total_count || 0);
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

    // Update labels
    document.getElementById('email-audience-type-label').textContent = isOwn ? 'Clientii Tai' : 'Baza Marketplace';
    document.getElementById('email-price-per').textContent = isOwn
        ? AmbiletUtils.formatCurrency(servicePricing.email.own_per_email || 0.40)
        : AmbiletUtils.formatCurrency(servicePricing.email.marketplace_per_email || 0.50);
}

async function updateEmailAudienceCount() {
    const audienceType = document.querySelector('input[name="email_audience"]:checked')?.value || 'own';
    const eventId = document.getElementById('service-event').value;

    // Get values from searchable multiselects
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

    // Remove null values
    Object.keys(filters).forEach(k => filters[k] === null && delete filters[k]);

    try {
        const response = await AmbiletAPI.get('/organizer/services/email-audiences', filters);
        if (response.success && response.data) {
            let count = response.data.filtered_count || 0;
            const baseCount = response.data.total_count || 0;
            const fc = response.data.filter_counts || {};

            // Calculate partial matches extra count
            let partialExtra = 0;
            const maxWithout = Math.max(
                fc.without_city || 0,
                fc.without_category || 0,
                fc.without_genre || 0
            );
            partialExtra = Math.max(0, maxWithout - count);

            // Check if include partial matches is enabled
            const includePartial = document.getElementById('include-partial-matches')?.checked;
            const perfectCount = count; // Before adding partial
            let partialCount = 0;
            if (includePartial && partialExtra > 0) {
                partialCount = partialExtra;
                count = perfectCount + partialCount;
            }

            // Update UI counts
            document.getElementById('audience-filtered-count').textContent = AmbiletUtils.formatNumber(count);
            document.getElementById('email-recipient-count').textContent = AmbiletUtils.formatNumber(count);

            if (audienceType === 'own') {
                document.getElementById('audience-own-count').textContent = AmbiletUtils.formatNumber(baseCount);
                emailAudiences.own.count = baseCount;
                emailAudiences.own.filtered_count = count;
                emailAudiences.own.perfect_count = perfectCount;
                emailAudiences.own.partial_count = partialCount;
            } else {
                document.getElementById('audience-marketplace-count').textContent = '~' + AmbiletUtils.formatNumber(baseCount);
                emailAudiences.marketplace.count = baseCount;
                emailAudiences.marketplace.filtered_count = count;
                emailAudiences.marketplace.perfect_count = perfectCount;
                emailAudiences.marketplace.partial_count = partialCount;
            }

            // Calculate cost with separate pricing for partial matches (half price)
            const pricePerEmail = audienceType === 'own'
                ? (servicePricing.email.own_per_email || 0.40)
                : (servicePricing.email.marketplace_per_email || 0.50);
            const partialPrice = Math.round((pricePerEmail / 2) * 100) / 100;
            const perfectCost = perfectCount * pricePerEmail;
            const partialCostVal = Math.round(partialCount * partialPrice * 100) / 100;
            const totalCost = perfectCost + partialCostVal;
            document.getElementById('email-cost-estimate').textContent = AmbiletUtils.formatCurrency(totalCost);

            // Update pricing breakdown UI
            if (includePartial && partialCount > 0) {
                document.getElementById('email-recipient-row-simple').classList.add('hidden');
                document.getElementById('email-pricing-breakdown').classList.remove('hidden');
                document.getElementById('email-perfect-count').textContent = AmbiletUtils.formatNumber(perfectCount);
                document.getElementById('email-perfect-price').textContent = pricePerEmail.toFixed(2);
                document.getElementById('email-perfect-cost').textContent = AmbiletUtils.formatCurrency(perfectCost);
                document.getElementById('email-partial-count').textContent = AmbiletUtils.formatNumber(partialCount);
                document.getElementById('email-partial-price').textContent = partialPrice.toFixed(2);
                document.getElementById('email-partial-cost').textContent = AmbiletUtils.formatCurrency(partialCostVal);
            } else {
                document.getElementById('email-recipient-row-simple').classList.remove('hidden');
                document.getElementById('email-pricing-breakdown').classList.add('hidden');
            }

            // Show filter breakdowns
            displayFilterBreakdowns(fc, baseCount, partialExtra);
        }
    } catch (e) {
        console.log('Audience count error:', e.message);
        // Use cached values
        const count = emailAudiences[audienceType]?.filtered_count || 0;
        document.getElementById('audience-filtered-count').textContent = AmbiletUtils.formatNumber(count);
        document.getElementById('email-recipient-count').textContent = AmbiletUtils.formatNumber(count);

        const pricePerEmail = audienceType === 'own'
            ? (servicePricing.email.own_per_email || 0.40)
            : (servicePricing.email.marketplace_per_email || 0.50);
        document.getElementById('email-cost-estimate').textContent = AmbiletUtils.formatCurrency(count * pricePerEmail);
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

    // City breakdowns
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
            const countStr = typeof count === 'number' ? AmbiletUtils.formatNumber(count) : '0';
            div.innerHTML = '<span class="text-muted">' + cityName + '</span><span class="font-medium text-secondary">' + countStr + '</span>';
            cityItems.appendChild(div);
        }
        if (filterCounts.without_city !== undefined) {
            withoutCityEl.textContent = 'Fara filtru oras: ' + AmbiletUtils.formatNumber(filterCounts.without_city) + ' se potrivesc partial';
            withoutCityEl.classList.remove('hidden');
        } else {
            withoutCityEl.classList.add('hidden');
        }
    } else {
        citySection.classList.add('hidden');
    }

    // Category breakdown
    const catSection = document.getElementById('breakdown-category');
    const withoutCatEl = document.getElementById('breakdown-without-category');
    if (filterCounts.without_category !== undefined) {
        catSection.classList.remove('hidden');
        withoutCatEl.textContent = 'Fara filtru categorie: ' + AmbiletUtils.formatNumber(filterCounts.without_category) + ' se potrivesc partial';
    } else {
        catSection.classList.add('hidden');
    }

    // Genre breakdown
    const genreSection = document.getElementById('breakdown-genre');
    const withoutGenreEl = document.getElementById('breakdown-without-genre');
    if (filterCounts.without_genre !== undefined) {
        genreSection.classList.remove('hidden');
        withoutGenreEl.textContent = 'Fara filtru gen muzical: ' + AmbiletUtils.formatNumber(filterCounts.without_genre) + ' se potrivesc partial';
    } else {
        genreSection.classList.add('hidden');
    }

    // Birth date info
    const bdSection = document.getElementById('breakdown-birthdate');
    const bdText = document.getElementById('breakdown-birthdate-text');
    if (filterCounts.with_birth_date !== undefined && totalCount > 0) {
        const pct = Math.round((filterCounts.with_birth_date / totalCount) * 100);
        bdSection.classList.remove('hidden');
        bdText.textContent = AmbiletUtils.formatNumber(filterCounts.with_birth_date) + ' din ' + AmbiletUtils.formatNumber(totalCount) + ' (' + pct + '%) au data nasterii setata (relevanta pentru filtru varsta)';
    } else {
        bdSection.classList.add('hidden');
    }

    // Partial matches toggle
    const partialToggle = document.getElementById('partial-matches-toggle');
    if (partialExtra > 0) {
        partialToggle.classList.remove('hidden');
        document.getElementById('partial-matches-count').textContent = AmbiletUtils.formatNumber(partialExtra);
    } else {
        partialToggle.classList.add('hidden');
    }
}

document.getElementById('service-event').addEventListener('change', function() {
    const event = events.find(e => e.id == this.value);
    if (event) {
        document.getElementById('event-preview').classList.remove('hidden');
        document.getElementById('event-image').src = getStorageUrl(event.image);
        document.getElementById('event-title').textContent = event.name || event.title || '';
        const eventDate = event.starts_at || event.date;
        document.getElementById('event-date').textContent = eventDate ? AmbiletUtils.formatDate(eventDate) : '';
        document.getElementById('event-venue').textContent = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || '';
        // Update email audience counts for this event
        updateEmailAudienceCount();
    } else {
        document.getElementById('event-preview').classList.add('hidden');
    }
});

document.getElementById('service-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const payBtn = document.getElementById('btn-pay');
    const originalBtnText = payBtn.innerHTML;
    payBtn.disabled = true;
    payBtn.innerHTML = `
        <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Se proceseaza...
    `;

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

    const data = {
        service_type: currentServiceType,
        event_id: document.getElementById('service-event').value,
        payment_method: paymentMethod
    };

    // Add service-specific data nested under "config" (required by server)
    switch (currentServiceType) {
        case 'featuring': {
            const featStartVal = document.getElementById('featuring-start').value;
            const featEndVal = document.getElementById('featuring-end').value;
            const todayStr = new Date().toISOString().slice(0, 10);
            let startDatetime, endDatetime;

            if (featStartVal === todayStr) {
                // Start today: begins now, ends at same time on end date
                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                startDatetime = featStartVal + 'T' + hh + ':' + mm;
                endDatetime = featEndVal + 'T' + hh + ':' + mm;
            } else {
                // Future start: begins at 07:00, ends at 00:00 (midnight) on end date
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
        case 'tracking':
            data.config = {
                platforms: Array.from(document.querySelectorAll('input[name="tracking_platforms[]"]:checked')).map(c => c.value),
                duration_months: parseInt(document.getElementById('tracking-duration').value) || 1,
            };
            break;
        case 'campaign':
            data.config = {
                campaign_type: document.querySelector('input[name="campaign_type"]:checked').value,
                budget: document.getElementById('campaign-budget').value,
                notes: document.getElementById('campaign-notes').value,
            };
            break;
    }

    try {
        // Step 1: Create service order
        const response = await AmbiletAPI.post('/organizer/services/orders', data);

        if (!response.success) {
            throw new Error(response.message || 'Eroare la crearea comenzii');
        }

        const order = response.data.order;
        if (!order) {
            throw new Error('Nu s-a putut crea comanda');
        }

        // Step 2: Handle payment based on method
        if (paymentMethod === 'card' && order.total > 0) {
            // Initiate payment through marketplace payment gateway
            payBtn.innerHTML = `
                <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Se redirectioneaza catre plata...
            `;

            const eventId = document.getElementById('service-event').value;
            const payResponse = await AmbiletAPI.post(`/organizer/services/orders/${order.id}/pay`, {
                return_url: window.location.origin + '/organizator/services/success?order=' + order.id + '&type=' + currentServiceType + '&event=' + eventId,
                cancel_url: window.location.origin + '/organizator/services?cancelled=1'
            });

            if (payResponse.success && payResponse.data.payment_url) {
                // Check if payment requires POST form submission (e.g., Netopia)
                if (payResponse.data.method === 'POST' && payResponse.data.form_data) {
                    // Create and submit a form for Netopia
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
                    // Standard redirect for other payment processors
                    window.location.href = payResponse.data.payment_url;
                }
            } else {
                throw new Error(payResponse.message || 'Nu s-a putut initia plata');
            }
        } else if (paymentMethod === 'transfer') {
            // Bank transfer - show success message
            AmbiletNotifications.success('Comanda a fost inregistrata! Vei primi un email cu instructiunile de plata prin transfer bancar.');
            closeServiceModal();
            loadActiveServices();
        } else {
            // Free service or zero total
            AmbiletNotifications.success('Serviciul a fost activat cu succes!');
            closeServiceModal();
            loadActiveServices();
        }
    } catch (error) {
        console.error('Service order error:', error);
        AmbiletNotifications.error(error.message || 'Eroare la procesarea comenzii. Incearca din nou.');
        payBtn.disabled = false;
        payBtn.innerHTML = originalBtnText;
    }
});

function viewServiceDetails(id) {
    // Open service details modal or navigate to details page
    window.location.href = '/organizator/services/' + id;
}

document.getElementById('service-filter').addEventListener('change', function() {
    const type = this.value;
    const filtered = type ? activeServices.filter(s => s.type === type) : activeServices;
    const temp = activeServices;
    activeServices = filtered;
    renderActiveServices();
    activeServices = temp;
});

// Email Preview Functions - Subject & promo text variants per template
// The selected variant index is locked once chosen so preview = sent content

const emailSubjectVariants = {
    classic: [
        (n) => `${n} - Nu rata!`,
        (n) => `Esti pregatit? ${n} te asteapta!`,
        (n) => `Bilete disponibile: ${n}`,
        (n) => `Hai la ${n}! Asigura-ti locul`,
        (n) => `${n} - Evenimentul pe care nu vrei sa il ratezi`
    ],
    urgent: [
        (n) => `ULTIMELE BILETE pentru ${n}!`,
        (n) => `Stoc limitat! ${n} se vinde rapid`,
        (n) => `Nu rata ${n} - mai sunt putine bilete!`,
        (n) => `Ultimele locuri disponibile la ${n}`,
        (n) => `Grab ati biletele! ${n} aproape sold out`
    ],
    reminder: [
        (n) => `Reminder: ${n} este in curand!`,
        (n) => `Nu uita! ${n} se apropie`,
        (n) => `Mai sunt cateva zile pana la ${n}`,
        (n) => `${n} - inca mai poti obtine bilete`,
        (n) => `Pregateste-te pentru ${n}!`
    ]
};

const emailPromoTexts = {
    classic: [
        'Evenimentul pe care il asteptai este aproape! Asigura-te ca ai bilete pentru a nu rata aceasta experienta unica.',
        'Un eveniment pe care nu vrei sa il ratezi. Rezerva-ti biletele acum si pregateste-te pentru o seara de neuitat!',
        'Vino sa traiesti o experienta memorabila! Biletele sunt disponibile, nu amana - asigura-ti locul chiar acum.',
        'Esti gata pentru o experienta extraordinara? Biletele se vand repede, asa ca nu ezita sa iti faci rezervarea.',
        'Fii parte din acest eveniment special! Profita de disponibilitate si cumpara biletele cat mai sunt locuri.'
    ],
    urgent: [
        'Biletele se vand rapid! Rezerva-ti locul acum pentru a nu ramane pe dinafara.',
        'Stocul este aproape epuizat! Nu mai sta pe ganduri - aceasta ar putea fi ultima ta sansa.',
        'Cererea este uriasa si locurile se termina! Actioneaza acum si nu rata acest eveniment.',
        'Ultimele bilete se vand chiar acum. Daca inca nu ti-ai asigurat locul, acum e momentul!',
        'Disponibilitatea scade rapid! Fiecare minut conteaza - cumpara biletele inainte sa fie prea tarziu.'
    ],
    reminder: [
        'Pregateste-te pentru o experienta de neuitat! Nu uita sa iti rezervi biletele daca nu ai facut-o deja.',
        'Evenimentul este chiar dupa colt! Daca nu ai bilete inca, mai ai sansa sa le obtii acum.',
        'Marcheaza-ti in calendar si nu rata! Biletele sunt inca disponibile pentru tine.',
        'Numaratoarea inversa a inceput! Ai tot ce iti trebuie? Daca nu, biletele te asteapta.',
        'Evenimentul se apropie cu pasi repezi. Asigura-te ca esti pregatit - cumpara bilete acum!'
    ]
};

// Locked variant indices - set once on first preview, used for the actual sent email
let lockedVariants = {};

function getLockedVariantIndex(templateType, variantType, maxLen) {
    const key = templateType + '_' + variantType;
    if (lockedVariants[key] === undefined) {
        lockedVariants[key] = Math.floor(Math.random() * maxLen);
    }
    return lockedVariants[key];
}

// Reset locked variants when template type changes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="email_template"]').forEach(radio => {
        radio.addEventListener('change', () => { lockedVariants = {}; });
    });
});

function buildVenueBox(event) {
    const venueName = event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || '';
    const venueCity = event.venue_city || (typeof event.venue === 'object' ? event.venue?.city : '') || '';
    if (!venueName) return '';
    return `
        <div class="p-4 mb-6 border border-gray-200 bg-gray-50 rounded-xl">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-gray-200 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-secondary">${venueName}</p>
                    ${venueCity ? `<p class="text-xs text-muted">${venueCity}</p>` : ''}
                </div>
            </div>
        </div>`;
}

function buildArtistsBox(event) {
    const artists = event.artists || event.artist_names || [];
    if (!artists || artists.length === 0) return '';
    const artistList = Array.isArray(artists) ? artists : [artists];
    return `
        <div class="p-4 mb-6 border border-purple-200 bg-purple-50 rounded-xl">
            <p class="mb-2 text-xs font-semibold tracking-wide text-purple-700 uppercase">Artisti</p>
            <div class="flex flex-wrap gap-2">
                ${artistList.map(a => {
                    const name = typeof a === 'object' ? (a.name || a.title || '') : a;
                    return name ? `<span class="inline-block px-3 py-1 text-xs font-medium text-purple-700 bg-purple-100 rounded-full">${name}</span>` : '';
                }).join('')}
            </div>
        </div>`;
}

function buildDescriptionBox(event) {
    const desc = event.short_description || event.description || '';
    if (!desc) return '';
    const truncated = desc.length > 300 ? desc.substring(0, 300) + '...' : desc;
    return `<p class="mb-6 text-sm text-muted">${truncated}</p>`;
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
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-secondary">${eventName}</h1>
            <div class="p-4 mb-6 bg-surface rounded-xl">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs tracking-wide uppercase text-muted">Data</p>
                        <p class="font-semibold text-secondary">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide uppercase text-muted">Locatie</p>
                        <p class="font-semibold text-secondary">${eventVenue}</p>
                    </div>
                </div>
            </div>
            ${buildDescriptionBox(event)}
            ${buildVenueBox(event)}
            ${buildArtistsBox(event)}
            <p class="mb-6 text-center text-muted">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">Cumpara Bilete Acum</a>
            </div>
            <hr class="my-6 border-border">
            <p class="text-xs text-center text-muted">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
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
            <div class="p-4 mb-6 border border-red-200 bg-red-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-500 rounded-full">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-red-700">Ultimele bilete disponibile!</p>
                        <p class="text-sm text-red-600">Nu rata sansa de a fi acolo</p>
                    </div>
                </div>
            </div>
            <div class="mb-6 text-center">
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-secondary">${eventName}</h1>
            <div class="p-4 mb-6 bg-surface rounded-xl">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs tracking-wide uppercase text-muted">Data</p>
                        <p class="font-semibold text-secondary">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide uppercase text-muted">Locatie</p>
                        <p class="font-semibold text-secondary">${eventVenue}</p>
                    </div>
                </div>
            </div>
            <p class="mb-4 text-center text-muted">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-4 text-lg font-bold text-white transition-colors bg-red-500 rounded-xl hover:bg-red-600 animate-pulse">Cumpara ACUM - Stoc Limitat!</a>
            </div>
            <hr class="my-6 border-border">
            <p class="text-xs text-center text-muted">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
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
            <div class="p-4 mb-6 border border-blue-200 bg-blue-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-blue-500 rounded-full">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-blue-700">Evenimentul se apropie!</p>
                        <p class="text-sm text-blue-600">Inca mai poti obtine bilete</p>
                    </div>
                </div>
            </div>
            <h1 class="mb-4 text-2xl font-bold text-center text-secondary">${eventName}</h1>
            <div class="mb-6 text-center">
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-full max-w-md mx-auto shadow-lg rounded-xl">
            </div>
            <div class="p-6 mb-6 text-center text-white bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl">
                <p class="text-sm tracking-wide uppercase opacity-80">Marcheaza in calendar</p>
                <p class="my-2 text-3xl font-bold">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                <p class="text-lg">${eventVenue}</p>
            </div>
            ${buildDescriptionBox(event)}
            ${buildVenueBox(event)}
            ${buildArtistsBox(event)}
            <p class="mb-6 text-center text-muted">${variants[idx]}</p>
            <div class="mb-6 text-center">
                <a href="#" class="inline-block px-8 py-3 font-semibold text-white transition-colors bg-blue-500 rounded-xl hover:bg-blue-600">Vezi Detalii & Cumpara Bilete</a>
            </div>
            <hr class="my-6 border-border">
            <p class="text-xs text-center text-muted">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
            </p>`;
        }
    }
};

function showEmailPreview() {
    const eventId = document.getElementById('service-event').value;
    const event = events.find(e => e.id == eventId);
    if (!event) {
        AmbiletNotifications.error('Selecteaza un eveniment pentru a vedea previzualizarea');
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

    // Update preview header
    document.getElementById('preview-recipients').textContent = AmbiletUtils.formatNumber(recipientCount) + ' destinatari';
    document.getElementById('preview-subject').textContent = subjectText;

    // Generate email preview content (uses locked variants - same as what will be sent)
    const previewHtml = template.body(event, eventName, eventDate, eventVenue);
    document.getElementById('email-preview-content').innerHTML = previewHtml;

    // Store the locked subject + variant indices in a hidden field for order submission
    window._emailPreviewSubject = subjectText;
    window._emailPreviewVariants = { ...lockedVariants };

    // Show modal
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
        title: 'Prima pagina — Hero Banner',
        description: 'Evenimentul tau apare ca banner principal pe prima pagina, vizibil imediat la accesarea site-ului. Pozitia cu cea mai mare vizibilitate.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-gray-100 select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-primary">ambilet</span>
                    <div class="flex items-center gap-1 ml-2 text-gray-500">
                        <span class="px-2 py-1 rounded cursor-pointer">Concerte</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Festivaluri</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Teatru</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Sport</span>
                    </div>
                    <div class="flex items-center gap-2 ml-auto">
                        <div class="bg-gray-100 rounded-lg px-3 py-1.5 text-gray-400 flex items-center gap-1.5 w-28">
                            <svg class="flex-shrink-0 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cauta...</span>
                        </div>
                        <button class="bg-primary text-white px-3 py-1.5 rounded-lg font-medium cursor-default">Cont</button>
                    </div>
                </div>
                <!-- HERO BANNER — your event appears here -->
                <div class="relative ring-4 ring-primary ring-inset">
                    <img src="${eventImage}" alt="${eventName}" class="object-cover w-full h-56">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/50 to-transparent"></div>
                    <div class="absolute top-3 right-3 z-30 bg-yellow-400 text-yellow-900 text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-wide shadow-lg flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Evenimentul tau
                    </div>
                    <div class="absolute inset-0 flex flex-col justify-end p-5">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-[10px] text-yellow-400 font-bold uppercase tracking-widest">★ Promovat</span>
                            <span class="text-[10px] text-white/90">•</span>
                            <span class="text-[10px] text-white/90 uppercase tracking-wide">Concert</span>
                        </div>
                        <h2 class="max-w-sm mb-1 text-xl font-black leading-tight text-white drop-shadow-lg">${eventName}</h2>
                        <p class="flex items-center gap-2 mb-3 text-xs text-gray-300">
                            <span>${eventDate}</span><span>•</span><span>${eventVenue}</span>
                        </p>
                        <div class="flex items-center gap-3">
                            <button class="px-4 py-2 text-xs font-bold text-white rounded-lg shadow-lg cursor-default bg-primary">Cumpara Bilete</button>
                            <span class="text-xs font-semibold text-white/80">de la 80 RON</span>
                        </div>
                    </div>
                    <div class="absolute bottom-3 right-4 flex items-center gap-1.5">
                        <div class="w-5 h-1.5 bg-white rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
                        <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
                    </div>
                </div>
                <!-- Category tabs -->
                <div class="flex gap-2 px-4 py-2 overflow-x-auto bg-white border-b border-gray-200">
                    <span class="px-3 py-1 text-xs font-medium text-white rounded-full cursor-pointer bg-primary whitespace-nowrap">Toate</span>
                    <span class="px-3 py-1 text-xs text-gray-500 bg-gray-100 rounded-full cursor-pointer whitespace-nowrap">Concerte</span>
                    <span class="px-3 py-1 text-xs text-gray-500 bg-gray-100 rounded-full cursor-pointer whitespace-nowrap">Festivaluri</span>
                    <span class="px-3 py-1 text-xs text-gray-500 bg-gray-100 rounded-full cursor-pointer whitespace-nowrap">Teatru</span>
                    <span class="px-3 py-1 text-xs text-gray-500 bg-gray-100 rounded-full cursor-pointer whitespace-nowrap">Sport</span>
                    <span class="px-3 py-1 text-xs text-gray-500 bg-gray-100 rounded-full cursor-pointer whitespace-nowrap">Comedy</span>
                </div>
                <!-- Rest of page (dimmed) -->
                <div class="p-4 opacity-40">
                    <p class="mb-3 text-xs font-semibold tracking-wider text-gray-500 uppercase">Urmatoarele evenimente</p>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="overflow-hidden bg-white shadow-sm rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="overflow-hidden bg-white shadow-sm rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="overflow-hidden bg-white shadow-sm rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="overflow-hidden bg-white shadow-sm rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev4/300/180" class="object-cover w-full h-14"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    home_recommendations: {
        title: 'Prima pagina — Sectiunea Recomandari',
        description: 'Evenimentul tau apare primul in sectiunea "Recomandate pentru tine" de pe prima pagina, vizibila pentru toti vizitatorii site-ului.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-gray-100 select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-primary">ambilet</span>
                    <div class="flex items-center gap-1 ml-2 text-gray-500">
                        <span class="px-2 py-1 rounded cursor-pointer">Concerte</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Festivaluri</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Teatru</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-gray-100 rounded-lg px-3 py-1.5 text-gray-400 flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cauta...</span>
                        </div>
                    </div>
                </div>
                <!-- Hero (dimmed — not this placement) -->
                <div class="relative pointer-events-none opacity-30">
                    <img src="https://picsum.photos/seed/ambilet-hero2/900/300" class="object-cover w-full h-28">
                    <div class="absolute inset-0 flex flex-col justify-end p-4 bg-gradient-to-r from-black/70 to-transparent">
                        <div class="w-40 h-3 mb-1 rounded bg-white/40"></div>
                        <div class="w-24 h-2 rounded bg-white/30"></div>
                    </div>
                </div>
                <!-- RECOMMENDATIONS SECTION — your event appears here -->
                <div class="px-4 pt-5 pb-4 bg-white ring-4 ring-primary ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base text-primary">⭐</span>
                            <span class="font-bold text-gray-900">Recomandate pentru tine</span>
                            <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wide">Evenimentul tau</span>
                        </div>
                        <span class="text-xs font-medium cursor-pointer text-primary">Vezi toate →</span>
                    </div>
                    <div class="flex gap-3 pb-1 overflow-x-auto">
                        <!-- YOUR EVENT — highlighted, first position -->
                        <div class="relative flex-shrink-0 w-40 overflow-hidden bg-white shadow-lg cursor-pointer rounded-xl ring-2 ring-primary">
                            <div class="absolute top-1.5 right-1.5 z-10 bg-primary text-white text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${eventName}" class="object-cover w-full h-24">
                            <div class="p-2.5">
                                <p class="mb-1 text-xs font-bold leading-tight text-gray-900 truncate">${eventName}</p>
                                <p class="text-[10px] text-gray-500 mb-0.5">${eventDate}</p>
                                <p class="text-[10px] text-gray-400 truncate mb-1.5">${eventVenue}</p>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-primary">80 RON</p>
                                    <button class="text-[10px] bg-primary text-white px-2 py-0.5 rounded-lg cursor-default font-semibold">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <!-- Other events (dimmed) -->
                        <div class="flex-shrink-0 overflow-hidden bg-white shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-white shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-white shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="flex-shrink-0 overflow-hidden bg-white shadow-sm cursor-pointer w-36 rounded-xl opacity-45"><img src="https://picsum.photos/seed/ambilet-ev4/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-2 bg-gray-100 rounded"></div></div></div>
                    </div>
                </div>
                <!-- Rest of page (dimmed) -->
                <div class="p-4 mt-2 bg-white opacity-35">
                    <div class="w-40 h-3 mb-3 bg-gray-200 rounded"></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev5/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev6/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev7/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 bg-gray-100 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    category: {
        title: 'Pagina Categorie Eveniment',
        description: 'Evenimentul tau apare ca eveniment recomandat la inceputul paginii de categorie (ex: Concerte, Festivaluri). Ajunge la publicul targetat pe tipul de eveniment.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-gray-100 select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-primary">ambilet</span>
                    <div class="flex items-center gap-1 ml-2 text-gray-400">
                        <span class="cursor-pointer hover:text-primary">Acasa</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="font-semibold text-gray-700">Concerte</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-gray-100 rounded-lg px-3 py-1.5 text-gray-400 flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cauta...</span>
                        </div>
                    </div>
                </div>
                <!-- Category header -->
                <div class="px-5 py-4 bg-white border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 shadow-sm bg-gradient-to-br from-orange-500 to-red-500 rounded-xl">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </div>
                        <div>
                            <h1 class="text-base font-black text-gray-900">Concerte</h1>
                            <p class="text-xs text-gray-500">247 de evenimente disponibile</p>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3 overflow-x-auto">
                        <span class="px-3 py-1 bg-orange-500 text-white text-[11px] rounded-full font-medium whitespace-nowrap cursor-pointer">Toate concertele</span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[11px] rounded-full whitespace-nowrap cursor-pointer">Rock</span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[11px] rounded-full whitespace-nowrap cursor-pointer">Pop</span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[11px] rounded-full whitespace-nowrap cursor-pointer">Electronic</span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[11px] rounded-full whitespace-nowrap cursor-pointer">Jazz</span>
                    </div>
                </div>
                <!-- FEATURED EVENT — your event appears here -->
                <div class="p-4 bg-white ring-4 ring-primary ring-inset">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] font-black text-primary uppercase tracking-wider">★ Eveniment Promovat</span>
                        <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-1.5 py-0.5 rounded-full">Evenimentul tau</span>
                    </div>
                    <div class="relative overflow-hidden bg-white shadow-lg cursor-pointer rounded-2xl ring-2 ring-primary">
                        <div class="absolute top-2.5 right-2.5 z-10 bg-primary text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-md">★ PROMOVAT</div>
                        <div class="flex">
                            <img src="${eventImage}" alt="${eventName}" class="flex-shrink-0 object-cover w-32 h-28">
                            <div class="flex-1 p-3">
                                <p class="font-black text-gray-900 text-sm mb-0.5 leading-tight">${eventName}</p>
                                <div class="flex items-center gap-1 text-[11px] text-gray-500 mb-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${eventDate}
                                </div>
                                <div class="flex items-center gap-1 text-[11px] text-gray-500 mb-2">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    ${eventVenue}
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] text-gray-400">de la</p>
                                        <p class="text-sm font-black text-primary">80 RON</p>
                                    </div>
                                    <button class="bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-xl cursor-default shadow-sm">Cumpara Bilete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Other events (dimmed) -->
                <div class="p-4 opacity-35">
                    <p class="mb-3 text-xs font-semibold text-gray-500">Toate concertele</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="overflow-hidden bg-white shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-3 rounded bg-primary/20"></div></div></div>
                        <div class="overflow-hidden bg-white shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-3 rounded bg-primary/20"></div></div></div>
                        <div class="overflow-hidden bg-white shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="object-cover w-full h-20"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="w-2/3 h-2 mb-1 bg-gray-100 rounded"></div><div class="w-1/2 h-3 rounded bg-primary/20"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    city: {
        title: 'Pagina Oras Eveniment',
        description: 'Evenimentul tau apare in sectiunea "Populare in [oras]" pe pagina dedicata orasului evenimentului. Ajunge la utilizatorii care cauta evenimente in zona lor.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="text-sm bg-gray-100 select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="text-base font-black tracking-tight text-primary">ambilet</span>
                    <div class="flex items-center gap-1 ml-2 text-gray-400">
                        <span class="cursor-pointer hover:text-primary">Acasa</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="font-semibold text-gray-700">București</span>
                    </div>
                    <div class="ml-auto">
                        <div class="bg-gray-100 rounded-lg px-3 py-1.5 text-gray-400 flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cauta...</span>
                        </div>
                    </div>
                </div>
                <!-- City hero -->
                <div class="px-5 py-4 text-white bg-gradient-to-r from-blue-600 to-cyan-500">
                    <div class="flex items-center gap-2 mb-0.5">
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-lg font-black">București</span>
                    </div>
                    <p class="mb-3 text-xs text-blue-100">Descopera cele mai bune evenimente din oras</p>
                    <div class="flex gap-2">
                        <span class="px-2.5 py-1 bg-white/25 text-white text-[11px] rounded-full font-medium cursor-pointer">Toate</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Concerte</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Weekend</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Gratuit</span>
                    </div>
                </div>
                <!-- FEATURED IN CITY — your event appears here -->
                <div class="px-4 pt-4 pb-4 bg-white ring-4 ring-blue-500 ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔥</span>
                            <span class="font-bold text-gray-900">Populare in București</span>
                            <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-1.5 py-0.5 rounded-full">Evenimentul tau</span>
                        </div>
                        <span class="text-xs font-medium text-blue-600 cursor-pointer">Toate →</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <!-- YOUR EVENT — highlighted, first position -->
                        <div class="relative col-span-2 overflow-hidden bg-white shadow-lg cursor-pointer rounded-2xl ring-2 ring-blue-500">
                            <div class="absolute top-2 left-2 z-10 bg-yellow-400 text-yellow-900 text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">Evenimentul tau</div>
                            <div class="absolute top-2 right-2 z-10 bg-blue-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${eventName}" class="object-cover w-full h-28">
                            <div class="p-3">
                                <p class="mb-1 text-sm font-black leading-tight text-gray-900">${eventName}</p>
                                <p class="text-[11px] text-gray-500 mb-0.5">${eventDate}</p>
                                <p class="text-[11px] text-gray-400 truncate mb-2">${eventVenue}</p>
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-black text-blue-600">de la 80 RON</p>
                                    <button class="bg-blue-500 text-white text-[11px] font-bold px-3 py-1 rounded-lg cursor-default shadow-sm">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <!-- Other city events (dimmed) -->
                        <div class="space-y-2 opacity-40">
                            <div class="overflow-hidden bg-white shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2 mb-1 bg-gray-200 rounded"></div><div class="h-1.5 bg-gray-100 rounded w-2/3"></div></div></div>
                            <div class="overflow-hidden bg-white shadow-sm cursor-pointer rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="object-cover w-full h-16"><div class="p-2"><div class="h-2 mb-1 bg-gray-200 rounded"></div><div class="h-1.5 bg-gray-100 rounded w-2/3"></div></div></div>
                        </div>
                    </div>
                </div>
                <!-- More events in city (dimmed) -->
                <div class="p-4 mt-2 bg-white opacity-35">
                    <div class="w-48 h-3 mb-3 bg-gray-200 rounded"></div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev3/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-gray-200 rounded"></div></div></div>
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev4/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-gray-200 rounded"></div></div></div>
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev5/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-gray-200 rounded"></div></div></div>
                        <div class="overflow-hidden bg-gray-50 rounded-xl"><img src="https://picsum.photos/seed/ambilet-ev6/200/150" class="object-cover w-full h-12"><div class="p-1.5"><div class="h-2 mb-1 bg-gray-200 rounded"></div></div></div>
                    </div>
                </div>
            </div>
        `
    }
};

function showPlacementPreview(placement) {
    const preview = placementPreviews[placement];
    if (!preview) return;

    // Use selected event data for a realistic preview
    const eventId = document.getElementById('service-event')?.value;
    const event = events.find(e => e.id == eventId);
    const eventName = event ? (event.name || event.title || 'Evenimentul Tau') : 'Evenimentul Tau';
    const rawImage = event?.image ? getStorageUrl(event.image) : null;
    const eventImage = rawImage || 'https://picsum.photos/seed/ambilet-hero/900/450';
    const eventDate = event ? (AmbiletUtils.formatDate(event.starts_at || event.date) || 'Data evenimentului') : 'Data evenimentului';
    const eventVenue = event
        ? (event.venue_name || (typeof event.venue === 'object' ? event.venue?.name : event.venue) || event.venue_city || 'Locatia evenimentului')
        : 'Locatia evenimentului';

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
