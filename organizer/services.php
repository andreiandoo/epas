<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Servicii Extra';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Success Messages -->
            <div id="success-banner" class="hidden bg-success/10 border border-success/30 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-success rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-success" id="success-title">Succes!</p>
                        <p class="text-sm text-success/80" id="success-message">Operatiunea a fost finalizata cu succes.</p>
                    </div>
                    <button onclick="closeSuccessBanner()" class="p-2 hover:bg-success/10 rounded-lg">
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Cancelled Message -->
            <div id="cancelled-banner" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-amber-800">Plata Anulata</p>
                        <p class="text-sm text-amber-700">Plata a fost anulata. Poti incerca din nou oricand doresti.</p>
                    </div>
                    <button onclick="closeCancelledBanner()" class="p-2 hover:bg-amber-100 rounded-lg">
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Event Featuring -->
                <div class="bg-white flex flex-col justify-between rounded-2xl border border-border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-secondary mb-1">Promovare Eveniment</h3>
                                <p class="text-sm text-muted mb-4">Afiseaza evenimentul tau pe prima pagina, in sectiunea de recomandari, pe pagina categoriei sau a orasului evenimentului.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Hero Prima Pagina</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Recomandari</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Categorie</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Oras</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-primary" id="card-featuring-price">40 RON</span> / zi</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-surface border-t border-border">
                        <button onclick="openServiceModal('featuring')" class="btn btn-primary w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Cumpara Promovare
                        </button>
                    </div>
                </div>

                <!-- Email Marketing -->
                <div class="bg-white flex flex-col justify-between rounded-2xl border border-border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-accent to-orange-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-secondary mb-1">Email Marketing</h3>
                                <p class="text-sm text-muted mb-4">Trimite emailuri targetate catre baza noastra de utilizatori sau doar catre clientii tai anteriori.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 bg-accent/10 text-accent text-xs rounded-full">Baza Completa</span>
                                    <span class="px-2 py-1 bg-accent/10 text-accent text-xs rounded-full">Audienta Filtrata</span>
                                    <span class="px-2 py-1 bg-accent/10 text-accent text-xs rounded-full">Clientii Tai</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-accent" id="card-email-price">0.40 RON</span> / email</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-surface border-t border-border">
                        <button onclick="openServiceModal('email')" class="btn btn-accent w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Cumpara Campanie Email
                        </button>
                    </div>
                </div>

                <!-- Ad Tracking -->
                <div class="bg-white flex flex-col justify-between rounded-2xl border border-border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-secondary mb-1">Tracking Campanii Ads</h3>
                                <p class="text-sm text-muted mb-4">Conecteaza campaniile tale Facebook, Google sau TikTok pentru a urmari conversiile si ROI-ul.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">Facebook Ads</span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">Google Ads</span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">TikTok Ads</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-blue-600">99 RON</span> / luna</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-surface border-t border-border">
                        <button onclick="openServiceModal('tracking')" class="btn w-full bg-blue-600 text-white hover:bg-blue-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Activeaza Tracking
                        </button>
                    </div>
                </div>

                <!-- Ad Campaign Creation -->
                <div class="bg-white flex flex-col justify-between rounded-2xl border border-border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-secondary mb-1">Creare Campanii Ads</h3>
                                <p class="text-sm text-muted mb-4">Lasa echipa noastra sa creeze si sa gestioneze campanii publicitare profesionale pentru evenimentul tau.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Strategie Completa</span>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Design Creativ</span>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Management</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-purple-600">499 RON</span> / campanie</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-surface border-t border-border">
                        <button onclick="openServiceModal('campaign')" class="btn w-full bg-purple-600 text-white hover:bg-purple-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Solicita Campanie
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Services -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Servicii Active</h2>
                    <select id="service-filter" class="input">
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
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Serviciu</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Detalii</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Perioada</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody id="services-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Service Modal -->
    <div id="service-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-border flex items-center justify-between">
                <h3 id="modal-title" class="text-xl font-bold text-secondary">Configureaza Serviciu</h3>
                <button onclick="closeServiceModal()" class="p-2 hover:bg-surface rounded-lg">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Step Indicator -->
            <div class="px-6 py-4 border-b border-border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2" id="step-1-indicator">
                        <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">1</div>
                        <span class="text-sm font-medium text-secondary">Selecteaza Eveniment</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-border mx-4"></div>
                    <div class="flex items-center gap-2" id="step-2-indicator">
                        <div class="w-8 h-8 rounded-full bg-border text-muted flex items-center justify-center text-sm font-bold">2</div>
                        <span class="text-sm font-medium text-muted">Configureaza</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-border mx-4"></div>
                    <div class="flex items-center gap-2" id="step-3-indicator">
                        <div class="w-8 h-8 rounded-full bg-border text-muted flex items-center justify-center text-sm font-bold">3</div>
                        <span class="text-sm font-medium text-muted">Plata</span>
                    </div>
                </div>
            </div>

            <form id="service-form" class="p-6">
                <input type="hidden" id="service-type">

                <!-- Step 1: Select Event -->
                <div id="step-1" class="step-content">
                    <label class="label">Selecteaza Evenimentul *</label>
                    <select id="service-event" class="input w-full mb-4" required>
                        <option value="">Alege un eveniment...</option>
                    </select>
                    <div id="event-preview" class="hidden bg-surface rounded-xl p-4 mb-4">
                        <div class="flex gap-4">
                            <img id="event-image" src="" alt="" class="w-20 h-20 rounded-lg object-cover">
                            <div>
                                <h4 id="event-title" class="font-semibold text-secondary"></h4>
                                <p id="event-date" class="text-sm text-muted"></p>
                                <p id="event-venue" class="text-sm text-muted"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Configuration (different for each service type) -->
                <div id="step-2" class="step-content hidden">
                    <!-- Featuring Options -->
                    <div id="featuring-options" class="hidden space-y-4">
                        <label class="label">Unde vrei sa apara evenimentul?</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="relative">
                                <label class="cursor-pointer block">
                                    <input type="checkbox" name="featuring_locations[]" value="home_hero" class="peer sr-only">
                                    <div class="p-4 pr-9 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Prima pagina - Hero</p>
                                        <p class="text-sm text-muted">Vizibilitate maxima, banner principal</p>
                                        <p class="text-sm font-semibold text-primary mt-2" data-price-key="home_hero">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('home_hero')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="cursor-pointer block">
                                    <input type="checkbox" name="featuring_locations[]" value="home_recommendations" class="peer sr-only">
                                    <div class="p-4 pr-9 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Prima pagina - Recomandari</p>
                                        <p class="text-sm text-muted">Sectiunea de recomandari</p>
                                        <p class="text-sm font-semibold text-primary mt-2" data-price-key="home_recommendations">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('home_recommendations')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="cursor-pointer block">
                                    <input type="checkbox" name="featuring_locations[]" value="category" class="peer sr-only">
                                    <div class="p-4 pr-9 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Pagina categorie eveniment</p>
                                        <p class="text-sm text-muted">Audienta targetata pe categorie</p>
                                        <p class="text-sm font-semibold text-primary mt-2" data-price-key="category">— RON / zi</p>
                                    </div>
                                </label>
                                <button type="button" onclick="showPlacementPreview('category')" class="absolute top-2 right-2 p-1.5 bg-white hover:bg-primary/5 border border-border rounded-lg text-muted hover:text-primary z-10 shadow-sm transition-colors" title="Previzualizeaza plasamentul">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="cursor-pointer block">
                                    <input type="checkbox" name="featuring_locations[]" value="city" class="peer sr-only">
                                    <div class="p-4 pr-9 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                        <p class="font-medium text-secondary">Pagina oras eveniment</p>
                                        <p class="text-sm text-muted">Audienta locala din orasul tau</p>
                                        <p class="text-sm font-semibold text-primary mt-2" data-price-key="city">— RON / zi</p>
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
                                <input type="date" id="featuring-start" class="input w-full" required>
                            </div>
                            <div>
                                <label class="label">Data Sfarsit *</label>
                                <input type="date" id="featuring-end" class="input w-full" required>
                            </div>
                        </div>
                    </div>

                    <!-- Email Marketing Options -->
                    <div id="email-options" class="hidden space-y-4">
                        <label class="label">Selecteaza Audienta</label>
                        <div class="space-y-3">
                            <label class="cursor-pointer block">
                                <input type="radio" name="email_audience" value="own" class="peer sr-only" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="font-medium text-secondary">Clientii Tai</p>
                                            <p class="text-sm text-muted">Participantii de la evenimentele tale anterioare</p>
                                            <p class="text-xs text-accent font-semibold mt-1">0.40 RON / email</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-own-count">0</p>
                                            <p class="text-xs text-muted">clienti</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="cursor-pointer block">
                                <input type="radio" name="email_audience" value="marketplace" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="font-medium text-secondary">Baza de Date Marketplace</p>
                                            <p class="text-sm text-muted">Toti utilizatorii activi din platforma</p>
                                            <p class="text-xs text-accent font-semibold mt-1">0.50 RON / email</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-marketplace-count">~250,000</p>
                                            <p class="text-xs text-muted">utilizatori</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Audience Filters -->
                        <div class="border border-border rounded-xl p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-secondary">Filtreaza Audienta</p>
                                <button type="button" onclick="resetEmailFilters()" class="text-sm text-primary hover:underline">Reseteaza filtre</button>
                            </div>

                            <!-- Age Range -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label text-xs">Varsta minima</label>
                                    <select id="email-filter-age-min" class="input w-full text-sm" onchange="updateEmailAudienceCount()">
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
                                    <label class="label text-xs">Varsta maxima</label>
                                    <select id="email-filter-age-max" class="input w-full text-sm" onchange="updateEmailAudienceCount()">
                                        <option value="">Orice</option>
                                        <option value="25">pana la 25</option>
                                        <option value="30">pana la 30</option>
                                        <option value="35">pana la 35</option>
                                        <option value="40">pana la 40</option>
                                        <option value="50">pana la 50</option>
                                        <option value="65">pana la 65</option>
                                    </select>
                                </div>
                            </div>

                            <!-- City -->
                            <div>
                                <label class="label text-xs">Oras <span class="text-muted">(multiselect)</span></label>
                                <select id="email-filter-city" class="input w-full text-sm" multiple size="4" onchange="updateEmailAudienceCount()">
                                </select>
                            </div>

                            <!-- Event Type (Category) -->
                            <div>
                                <label class="label text-xs">Tip Eveniment <span class="text-muted">(multiselect)</span></label>
                                <select id="email-filter-category" class="input w-full text-sm" multiple size="4" onchange="updateEmailAudienceCount()">
                                </select>
                            </div>

                            <!-- Music Genre -->
                            <div>
                                <label class="label text-xs">Gen Muzical <span class="text-muted">(multiselect)</span></label>
                                <select id="email-filter-genre" class="input w-full text-sm" multiple size="4" onchange="updateEmailAudienceCount()">
                                </select>
                            </div>

                            <!-- Filtered Count -->
                            <div class="bg-accent/10 rounded-lg p-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-secondary font-medium">Audienta filtrata</p>
                                    <p class="text-xs text-muted">Pe baza filtrelor selectate</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-bold text-accent" id="audience-filtered-count">0</p>
                                    <p class="text-xs text-muted">destinatari</p>
                                </div>
                            </div>
                        </div>

                        <!-- Cost Summary -->
                        <div class="bg-surface rounded-xl p-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-muted">Tip audienta:</span>
                                <span class="text-sm font-semibold text-secondary" id="email-audience-type-label">Clientii Tai</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-muted">Cost per email:</span>
                                <span class="font-semibold text-secondary" id="email-price-per">0.40 RON</span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-muted">Nr. destinatari:</span>
                                <span class="font-semibold text-secondary" id="email-recipient-count">0</span>
                            </div>
                            <div class="border-t border-border pt-2 mt-2 flex justify-between items-center">
                                <span class="text-sm font-medium text-secondary">Cost total estimat:</span>
                                <span class="text-lg font-bold text-accent" id="email-cost-estimate">0 RON</span>
                            </div>
                        </div>

                        <div class="bg-blue-50 rounded-xl p-4">
                            <div class="flex gap-3">
                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-sm text-blue-800 font-medium">Confidentialitate garantata</p>
                                    <p class="text-xs text-blue-700 mt-1">Nu vei avea acces la datele personale ale utilizatorilor (nume, email, telefon). Emailurile sunt trimise direct prin platforma noastra.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Data Trimitere *</label>
                                <input type="date" id="email-send-date" class="input w-full" required>
                            </div>
                            <div>
                                <label class="label">Ora Trimitere *</label>
                                <input type="time" id="email-send-time" class="input w-full" value="10:00" required>
                            </div>
                        </div>
                        <p class="text-xs text-muted">Programeaza trimiterea pentru momentul optim (recomandat: 10:00 sau 18:00)</p>

                        <!-- Email Template Selection -->
                        <div class="border-t border-border pt-4 mt-4">
                            <label class="label">Alege Model Email</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="classic" class="peer sr-only" checked>
                                    <div class="p-3 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5 text-center">
                                        <div class="w-full h-16 bg-gradient-to-b from-accent/20 to-accent/5 rounded-lg mb-2 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <p class="text-xs font-medium text-secondary">Clasic</p>
                                        <p class="text-[10px] text-muted">Imagine + info</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="urgent" class="peer sr-only">
                                    <div class="p-3 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5 text-center">
                                        <div class="w-full h-16 bg-gradient-to-b from-red-500/20 to-red-500/5 rounded-lg mb-2 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <p class="text-xs font-medium text-secondary">Urgent</p>
                                        <p class="text-[10px] text-muted">Ultimele bilete</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="email_template" value="reminder" class="peer sr-only">
                                    <div class="p-3 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5 text-center">
                                        <div class="w-full h-16 bg-gradient-to-b from-blue-500/20 to-blue-500/5 rounded-lg mb-2 flex items-center justify-center">
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
                            <button type="button" onclick="showEmailPreview()" class="btn btn-secondary w-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Previzualizeaza Emailul
                            </button>
                            <p class="text-xs text-muted mt-1 text-center">Vezi cum va arata emailul inainte de a-l trimite</p>
                        </div>
                    </div>

                    <!-- Ad Tracking Options -->
                    <div id="tracking-options" class="hidden space-y-4">
                        <label class="label">Platforme de Tracking</label>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border border-border rounded-xl cursor-pointer hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="facebook" class="w-5 h-5 text-blue-600 rounded">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-secondary">Facebook Pixel</p>
                                    <p class="text-sm text-muted">Track conversii si retargeting</p>
                                </div>
                                <span class="text-sm font-semibold text-blue-600">49 RON / luna</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-border rounded-xl cursor-pointer hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="google" class="w-5 h-5 text-blue-600 rounded">
                                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-secondary">Google Ads</p>
                                    <p class="text-sm text-muted">Conversion tracking complet</p>
                                </div>
                                <span class="text-sm font-semibold text-blue-600">49 RON / luna</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-border rounded-xl cursor-pointer hover:border-blue-300">
                                <input type="checkbox" name="tracking_platforms[]" value="tiktok" class="w-5 h-5 text-blue-600 rounded">
                                <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
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
                            <select id="tracking-duration" class="input w-full">
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
                            <label class="cursor-pointer block">
                                <input type="radio" name="campaign_type" value="basic" class="peer sr-only" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Basic</p>
                                            <ul class="text-sm text-muted mt-2 space-y-1">
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
                            <label class="cursor-pointer block">
                                <input type="radio" name="campaign_type" value="standard" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Standard</p>
                                            <ul class="text-sm text-muted mt-2 space-y-1">
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
                            <label class="cursor-pointer block">
                                <input type="radio" name="campaign_type" value="premium" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-purple-500 peer-checked:bg-purple-50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-secondary">Campanie Premium</p>
                                            <ul class="text-sm text-muted mt-2 space-y-1">
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
                            <input type="number" id="campaign-budget" class="input w-full" min="500" value="1000" placeholder="Minim 500 RON">
                            <p class="text-sm text-muted mt-1">Acesta este bugetul pentru platirea reclamelor (separat de costul serviciului)</p>
                        </div>
                        <div>
                            <label class="label">Detalii Suplimentare</label>
                            <textarea id="campaign-notes" class="input w-full h-24" placeholder="Spune-ne mai multe despre obiectivele tale..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment -->
                <div id="step-3" class="step-content hidden">
                    <div class="bg-surface rounded-xl p-6 mb-6">
                        <h4 class="font-semibold text-secondary mb-4">Sumar Comanda</h4>
                        <div id="order-summary" class="space-y-3">
                            <!-- Summary will be populated dynamically -->
                        </div>
                        <div class="border-t border-border mt-4 pt-4">
                            <div class="flex justify-between items-center text-lg">
                                <span class="font-semibold text-secondary">Total de plata:</span>
                                <span class="font-bold text-primary" id="order-total">0 RON</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="label">Metoda de plata</label>
                        <div class="space-y-3">
                            <label class="cursor-pointer block">
                                <input type="radio" name="payment_method" value="card" class="peer sr-only" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5 flex items-center gap-4">
                                    <div class="w-16 h-10 bg-gradient-to-r from-green-600 to-green-700 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-[10px] font-bold">NETOPIA</span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-secondary">Card Bancar</p>
                                        <p class="text-xs text-muted">Visa, Mastercard, Maestro</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <div class="w-8 h-5 bg-blue-600 rounded flex items-center justify-center">
                                            <span class="text-white text-[8px] font-bold">VISA</span>
                                        </div>
                                        <div class="w-8 h-5 bg-red-500 rounded flex items-center justify-center">
                                            <span class="text-white text-[6px] font-bold">MC</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="cursor-pointer block">
                                <input type="radio" name="payment_method" value="transfer" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5 flex items-center gap-4">
                                    <div class="w-16 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-secondary">Transfer Bancar</p>
                                        <p class="text-xs text-muted">Activare in 1-2 zile lucratoare</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div id="card-payment-fields" class="bg-green-50 rounded-xl p-4">
                            <p class="text-sm text-green-800">Vei fi redirectionat catre Netopia Payments pentru a finaliza tranzactia in siguranta.</p>
                        </div>
                        <div id="transfer-payment-info" class="hidden bg-blue-50 rounded-xl p-4">
                            <p class="text-sm text-blue-800 font-medium mb-2">Detalii pentru transfer bancar:</p>
                            <p class="text-sm text-blue-700">IBAN: RO49 AAAA 1B31 0075 9384 0000</p>
                            <p class="text-sm text-blue-700">Banca: BCR</p>
                            <p class="text-sm text-blue-700">Beneficiar: Ambilet SRL</p>
                            <p class="text-sm text-blue-600 mt-2">Serviciul va fi activat dupa confirmarea platii (1-2 zile lucratoare).</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex gap-3 mt-6 pt-6 border-t border-border">
                    <button type="button" id="btn-back" onclick="prevStep()" class="btn btn-secondary flex-1 hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Inapoi
                    </button>
                    <button type="button" id="btn-next" onclick="nextStep()" class="btn btn-primary flex-1">
                        Continua
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <button type="submit" id="btn-pay" class="btn btn-primary flex-1 hidden">
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
            <div class="sticky top-0 bg-white p-6 border-b border-border flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-secondary">Previzualizare Email</h3>
                <button onclick="closeEmailPreview()" class="p-2 hover:bg-surface rounded-lg">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-gray-100 rounded-xl p-4 mb-4">
                    <p class="text-sm text-muted">Aceasta este o previzualizare a emailului care va fi trimis. Continutul final poate varia usor in functie de datele evenimentului.</p>
                </div>

                <!-- Email Preview Container -->
                <div class="border border-border rounded-xl overflow-hidden">
                    <!-- Email Header -->
                    <div class="bg-surface p-4 border-b border-border">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                <span class="text-white font-bold text-sm">A</span>
                            </div>
                            <div>
                                <p class="font-semibold text-secondary text-sm" id="preview-sender-name">Ambilet</p>
                                <p class="text-xs text-muted" id="preview-sender-email">noreply@ambilet.ro</p>
                            </div>
                        </div>
                        <p class="text-sm"><span class="text-muted">Catre:</span> <span class="text-secondary" id="preview-recipients">1,250 destinatari</span></p>
                        <p class="text-sm mt-1"><span class="text-muted">Subiect:</span> <span class="font-medium text-secondary" id="preview-subject">🎵 Nu rata evenimentul!</span></p>
                    </div>

                    <!-- Email Body -->
                    <div class="p-6 bg-white">
                        <div id="email-preview-content" class="space-y-4">
                            <!-- Preview will be rendered here -->
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button onclick="closeEmailPreview()" class="btn btn-secondary flex-1">Inchide</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Placement Preview Modal -->
    <div id="placement-preview-modal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-border flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-secondary" id="placement-preview-title">Previzualizare Plasament</h3>
                <button onclick="closePlacementPreview()" class="p-2 hover:bg-surface rounded-lg">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-blue-50 rounded-xl p-4 mb-6">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-sm text-blue-800 font-medium" id="placement-preview-description">Descriere plasament</p>
                        </div>
                    </div>
                </div>

                <!-- Preview Container -->
                <div id="placement-preview-content" class="border border-border rounded-xl overflow-hidden">
                    <!-- Preview will be rendered here -->
                </div>

                <div class="mt-6 flex gap-3">
                    <button onclick="closePlacementPreview()" class="btn btn-secondary flex-1">Inchide</button>
                </div>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

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
    marketplace: { count: 250000, filtered_count: 250000 }
};
let emailFilterOptions = {
    cities: [],
    categories: [],
    genres: []
};

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
        const response = await AmbiletAPI.get('/organizer/services');
        if (response.success) {
            activeServices = response.data.services || [];
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

    container.innerHTML = activeServices.map(s => `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <span class="px-3 py-1 bg-${typeColors[s.type]}/10 text-${typeColors[s.type]} text-sm font-medium rounded-full">${typeLabels[s.type]}</span>
            </td>
            <td class="px-6 py-4 font-medium text-secondary">${s.event_title}</td>
            <td class="px-6 py-4 text-sm text-muted">${s.details}</td>
            <td class="px-6 py-4 text-sm text-muted">${AmbiletUtils.formatDate(s.start_date)} - ${AmbiletUtils.formatDate(s.end_date)}</td>
            <td class="px-6 py-4">
                <span class="px-3 py-1 bg-${s.status === 'active' ? 'success' : s.status === 'pending' ? 'warning' : 'muted'}/10 text-${s.status === 'active' ? 'success' : s.status === 'pending' ? 'warning' : 'muted'} text-sm rounded-full">
                    ${s.status === 'active' ? 'Activ' : s.status === 'pending' ? 'In asteptare' : 'Finalizat'}
                </span>
            </td>
            <td class="px-6 py-4 text-right">
                <button onclick="viewServiceDetails(${s.id})" class="p-2 text-muted hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </td>
        </tr>
    `).join('');
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
            if (endDate < startDate) {
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
        case 'featuring':
            const locations = document.querySelectorAll('input[name="featuring_locations[]"]:checked');
            const startDate = new Date(document.getElementById('featuring-start').value);
            const endDate = new Date(document.getElementById('featuring-end').value);
            const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;

            const prices = servicePricing.featuring;
            const labels = {
                home_hero: 'Prima pagina - Hero',
                home_recommendations: 'Prima pagina - Recomandari',
                category: 'Pagina categorie eveniment',
                city: 'Pagina oras eveniment'
            };

            locations.forEach(loc => {
                const price = (prices[loc.value] ?? 40) * days;
                items.push({ name: (labels[loc.value] || loc.value) + ' (' + days + ' zile)', price });
                total += price;
            });
            break;

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
        // Load cities
        const citiesRes = await AmbiletAPI.get('/cities');
        if (citiesRes.success && citiesRes.data.cities) {
            emailFilterOptions.cities = citiesRes.data.cities;
            const citySelect = document.getElementById('email-filter-city');
            citiesRes.data.cities.forEach(city => {
                const opt = document.createElement('option');
                opt.value = city.slug || city.id;
                opt.textContent = city.name;
                citySelect.appendChild(opt);
            });
        }

        // Load categories
        const catRes = await AmbiletAPI.get('/event-categories');
        if (catRes.success && catRes.data.categories) {
            emailFilterOptions.categories = catRes.data.categories;
            const catSelect = document.getElementById('email-filter-category');
            catRes.data.categories.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.slug || cat.id;
                opt.textContent = cat.name;
                catSelect.appendChild(opt);
            });
        }

        // Load genres
        const genreRes = await AmbiletAPI.get('/event-genres');
        if (genreRes.success && genreRes.data.genres) {
            emailFilterOptions.genres = genreRes.data.genres;
            const genreSelect = document.getElementById('email-filter-genre');
            genreRes.data.genres.forEach(genre => {
                const opt = document.createElement('option');
                opt.value = genre.slug || genre.id;
                opt.textContent = genre.name;
                genreSelect.appendChild(opt);
            });
        }
    } catch (e) {
        console.log('Email filter options will load when API is available');
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

    // Get multiselect values as arrays
    const getSelectedValues = (selectId) => {
        const select = document.getElementById(selectId);
        return Array.from(select.selectedOptions).map(opt => opt.value).filter(v => v);
    };

    // Collect filters
    const cities = getSelectedValues('email-filter-city');
    const categories = getSelectedValues('email-filter-category');
    const genres = getSelectedValues('email-filter-genre');

    const filters = {
        audience_type: audienceType,
        event_id: eventId,
        age_min: document.getElementById('email-filter-age-min').value || null,
        age_max: document.getElementById('email-filter-age-max').value || null,
        cities: cities.length > 0 ? cities : null,
        categories: categories.length > 0 ? categories : null,
        genres: genres.length > 0 ? genres : null
    };

    // Remove null values
    Object.keys(filters).forEach(k => filters[k] === null && delete filters[k]);

    try {
        const response = await AmbiletAPI.get('/organizer/services/email-audiences', filters);
        if (response.success && response.data) {
            const count = response.data.filtered_count || 0;
            const baseCount = response.data.total_count || 0;

            // Update UI counts
            document.getElementById('audience-filtered-count').textContent = AmbiletUtils.formatNumber(count);
            document.getElementById('email-recipient-count').textContent = AmbiletUtils.formatNumber(count);

            if (audienceType === 'own') {
                document.getElementById('audience-own-count').textContent = AmbiletUtils.formatNumber(baseCount);
                emailAudiences.own.count = baseCount;
                emailAudiences.own.filtered_count = count;
            } else {
                document.getElementById('audience-marketplace-count').textContent = '~' + AmbiletUtils.formatNumber(baseCount);
                emailAudiences.marketplace.count = baseCount;
                emailAudiences.marketplace.filtered_count = count;
            }

            // Calculate cost
            const pricePerEmail = audienceType === 'own'
                ? (servicePricing.email.own_per_email || 0.40)
                : (servicePricing.email.marketplace_per_email || 0.50);
            const totalCost = count * pricePerEmail;
            document.getElementById('email-cost-estimate').textContent = AmbiletUtils.formatCurrency(totalCost);
        }
    } catch (e) {
        console.log('Using default audience counts');
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

function resetEmailFilters() {
    document.getElementById('email-filter-age-min').value = '';
    document.getElementById('email-filter-age-max').value = '';
    // Clear multiselect selections
    const citySelect = document.getElementById('email-filter-city');
    const categorySelect = document.getElementById('email-filter-category');
    const genreSelect = document.getElementById('email-filter-genre');
    Array.from(citySelect.options).forEach(opt => opt.selected = false);
    Array.from(categorySelect.options).forEach(opt => opt.selected = false);
    Array.from(genreSelect.options).forEach(opt => opt.selected = false);
    updateEmailAudienceCount();
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

    // Add service-specific data
    switch (currentServiceType) {
        case 'featuring':
            data.locations = Array.from(document.querySelectorAll('input[name="featuring_locations[]"]:checked')).map(c => c.value);
            data.start_date = document.getElementById('featuring-start').value;
            data.end_date = document.getElementById('featuring-end').value;
            break;
        case 'email':
            data.audience_type = document.querySelector('input[name="email_audience"]:checked').value;
            data.template = document.querySelector('input[name="email_template"]:checked')?.value || 'classic';
            // Combine date and time into ISO format
            const emailDate = document.getElementById('email-send-date').value;
            const emailTime = document.getElementById('email-send-time').value || '10:00';
            data.send_date = emailDate + 'T' + emailTime;
            data.recipient_count = emailAudiences[data.audience_type]?.filtered_count || 0;
            // Get multiselect values
            const getSelectedVals = (selectId) => {
                const select = document.getElementById(selectId);
                return Array.from(select.selectedOptions).map(opt => opt.value).filter(v => v);
            };
            data.filters = {
                age_min: document.getElementById('email-filter-age-min').value || null,
                age_max: document.getElementById('email-filter-age-max').value || null,
                cities: getSelectedVals('email-filter-city'),
                categories: getSelectedVals('email-filter-category'),
                genres: getSelectedVals('email-filter-genre')
            };
            break;
        case 'tracking':
            data.platforms = Array.from(document.querySelectorAll('input[name="tracking_platforms[]"]:checked')).map(c => c.value);
            data.duration = document.getElementById('tracking-duration').value;
            break;
        case 'campaign':
            data.campaign_type = document.querySelector('input[name="campaign_type"]:checked').value;
            data.budget = document.getElementById('campaign-budget').value;
            data.notes = document.getElementById('campaign-notes').value;
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

// Email Preview Functions
const emailTemplates = {
    classic: {
        subject: (eventName) => `🎵 ${eventName} - Nu rata!`,
        body: (event, eventName, eventDate, eventVenue) => `
            <div class="text-center mb-6">
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-full max-w-md mx-auto rounded-xl shadow-lg">
            </div>

            <h1 class="text-2xl font-bold text-secondary text-center mb-4">${eventName}</h1>

            <div class="bg-surface rounded-xl p-4 mb-6">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs text-muted uppercase tracking-wide">Data</p>
                        <p class="font-semibold text-secondary">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted uppercase tracking-wide">Locatie</p>
                        <p class="font-semibold text-secondary">${eventVenue}</p>
                    </div>
                </div>
            </div>

            <p class="text-muted text-center mb-6">
                Evenimentul pe care il asteptai este aproape! Asigura-te ca ai bilete pentru a nu rata aceasta experienta unica.
            </p>

            <div class="text-center mb-6">
                <a href="#" class="inline-block bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:bg-primary-dark transition-colors">
                    Cumpara Bilete Acum
                </a>
            </div>

            <hr class="border-border my-6">

            <p class="text-xs text-muted text-center">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
            </p>
        `
    },
    urgent: {
        subject: (eventName) => `⚡ ULTIMELE BILETE pentru ${eventName}!`,
        body: (event, eventName, eventDate, eventVenue) => `
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-red-700">Ultimele bilete disponibile!</p>
                        <p class="text-sm text-red-600">Nu rata sansa de a fi acolo</p>
                    </div>
                </div>
            </div>

            <div class="text-center mb-6">
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-full max-w-md mx-auto rounded-xl shadow-lg">
            </div>

            <h1 class="text-2xl font-bold text-secondary text-center mb-4">${eventName}</h1>

            <div class="bg-surface rounded-xl p-4 mb-6">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs text-muted uppercase tracking-wide">Data</p>
                        <p class="font-semibold text-secondary">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted uppercase tracking-wide">Locatie</p>
                        <p class="font-semibold text-secondary">${eventVenue}</p>
                    </div>
                </div>
            </div>

            <p class="text-muted text-center mb-4">
                Biletele se vand rapid! Rezerva-ti locul acum pentru a nu ramane pe dinafara.
            </p>

            <div class="text-center mb-6">
                <a href="#" class="inline-block bg-red-500 text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-red-600 transition-colors animate-pulse">
                    🔥 Cumpara ACUM - Stoc Limitat!
                </a>
            </div>

            <hr class="border-border my-6">

            <p class="text-xs text-muted text-center">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
            </p>
        `
    },
    reminder: {
        subject: (eventName) => `📅 Reminder: ${eventName} este in curand!`,
        body: (event, eventName, eventDate, eventVenue) => `
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-blue-700">🎉 Evenimentul se apropie!</p>
                        <p class="text-sm text-blue-600">Inca mai poti obtine bilete</p>
                    </div>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-secondary text-center mb-4">${eventName}</h1>

            <div class="flex items-center justify-center gap-2 mb-6">
                <img src="${getStorageUrl(event.image)}" alt="${eventName}" class="w-32 h-32 rounded-xl object-cover shadow-lg">
            </div>

            <div class="bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-xl p-6 mb-6 text-center">
                <p class="text-sm uppercase tracking-wide opacity-80">Marcheaza in calendar</p>
                <p class="text-3xl font-bold my-2">${eventDate ? AmbiletUtils.formatDate(eventDate) : 'TBA'}</p>
                <p class="text-lg">${eventVenue}</p>
            </div>

            <p class="text-muted text-center mb-6">
                Pregateste-te pentru o experienta de neuitat! Nu uita sa iti rezervi biletele daca nu ai facut-o deja.
            </p>

            <div class="text-center mb-6">
                <a href="#" class="inline-block bg-blue-500 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-600 transition-colors">
                    📍 Vezi Detalii & Cumpara Bilete
                </a>
            </div>

            <hr class="border-border my-6">

            <p class="text-xs text-muted text-center">
                Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                <a href="#" class="text-primary hover:underline">Dezabonare</a>
            </p>
        `
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

    // Update preview header
    document.getElementById('preview-recipients').textContent = AmbiletUtils.formatNumber(recipientCount) + ' destinatari';
    document.getElementById('preview-subject').textContent = template.subject(eventName);

    // Generate email preview content
    const previewHtml = template.body(event, eventName, eventDate, eventVenue);

    document.getElementById('email-preview-content').innerHTML = previewHtml;

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
            <div class="bg-gray-100 text-sm select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="font-black text-primary text-base tracking-tight">ambilet</span>
                    <div class="flex items-center gap-1 ml-2 text-gray-500">
                        <span class="px-2 py-1 rounded cursor-pointer">Concerte</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Festivaluri</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Teatru</span>
                        <span class="px-2 py-1 rounded cursor-pointer">Sport</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <div class="bg-gray-100 rounded-lg px-3 py-1.5 text-gray-400 flex items-center gap-1.5 w-28">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Cauta...</span>
                        </div>
                        <button class="bg-primary text-white px-3 py-1.5 rounded-lg font-medium cursor-default">Cont</button>
                    </div>
                </div>
                <!-- HERO BANNER — your event appears here -->
                <div class="relative ring-4 ring-primary ring-inset">
                    <img src="${eventImage}" alt="${eventName}" class="w-full h-56 object-cover">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/50 to-transparent"></div>
                    <div class="absolute top-3 right-3 z-30 bg-yellow-400 text-yellow-900 text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-wide shadow-lg flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Evenimentul tau
                    </div>
                    <div class="absolute inset-0 flex flex-col justify-end p-5">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-[10px] text-yellow-400 font-bold uppercase tracking-widest">★ Promovat</span>
                            <span class="text-[10px] text-white/50">•</span>
                            <span class="text-[10px] text-white/70 uppercase tracking-wide">Concert</span>
                        </div>
                        <h2 class="text-white text-xl font-black mb-1 max-w-sm drop-shadow-lg leading-tight">${eventName}</h2>
                        <p class="text-gray-300 text-xs mb-3 flex items-center gap-2">
                            <span>${eventDate}</span><span>•</span><span>${eventVenue}</span>
                        </p>
                        <div class="flex items-center gap-3">
                            <button class="bg-primary text-white text-xs font-bold px-4 py-2 rounded-lg shadow-lg cursor-default">Cumpara Bilete</button>
                            <span class="text-white/80 text-xs font-semibold">de la 80 RON</span>
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
                <div class="bg-white border-b border-gray-200 flex gap-2 px-4 py-2 overflow-x-auto">
                    <span class="px-3 py-1 bg-primary text-white text-xs rounded-full font-medium whitespace-nowrap cursor-pointer">Toate</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs rounded-full whitespace-nowrap cursor-pointer">Concerte</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs rounded-full whitespace-nowrap cursor-pointer">Festivaluri</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs rounded-full whitespace-nowrap cursor-pointer">Teatru</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs rounded-full whitespace-nowrap cursor-pointer">Sport</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs rounded-full whitespace-nowrap cursor-pointer">Comedy</span>
                </div>
                <!-- Rest of page (dimmed) -->
                <div class="p-4 opacity-40">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Urmatoarele evenimente</p>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="bg-white rounded-xl overflow-hidden shadow-sm"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="w-full h-14 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                        <div class="bg-white rounded-xl overflow-hidden shadow-sm"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="w-full h-14 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                        <div class="bg-white rounded-xl overflow-hidden shadow-sm"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="w-full h-14 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                        <div class="bg-white rounded-xl overflow-hidden shadow-sm"><img src="https://picsum.photos/seed/ambilet-ev4/300/180" class="w-full h-14 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    home_recommendations: {
        title: 'Prima pagina — Sectiunea Recomandari',
        description: 'Evenimentul tau apare primul in sectiunea "Recomandate pentru tine" de pe prima pagina, vizibila pentru toti vizitatorii site-ului.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="bg-gray-100 text-sm select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="font-black text-primary text-base tracking-tight">ambilet</span>
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
                <div class="relative opacity-30 pointer-events-none">
                    <img src="https://picsum.photos/seed/ambilet-hero2/900/300" class="w-full h-28 object-cover">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/70 to-transparent flex flex-col justify-end p-4">
                        <div class="h-3 bg-white/40 rounded w-40 mb-1"></div>
                        <div class="h-2 bg-white/30 rounded w-24"></div>
                    </div>
                </div>
                <!-- RECOMMENDATIONS SECTION — your event appears here -->
                <div class="bg-white px-4 pt-5 pb-4 ring-4 ring-primary ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-primary text-base">⭐</span>
                            <span class="font-bold text-gray-900">Recomandate pentru tine</span>
                            <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wide">Evenimentul tau</span>
                        </div>
                        <span class="text-xs text-primary cursor-pointer font-medium">Vezi toate →</span>
                    </div>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        <!-- YOUR EVENT — highlighted, first position -->
                        <div class="flex-shrink-0 w-40 bg-white rounded-xl shadow-lg ring-2 ring-primary overflow-hidden relative cursor-pointer">
                            <div class="absolute top-1.5 right-1.5 z-10 bg-primary text-white text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${eventName}" class="w-full h-24 object-cover">
                            <div class="p-2.5">
                                <p class="text-xs font-bold text-gray-900 truncate leading-tight mb-1">${eventName}</p>
                                <p class="text-[10px] text-gray-500 mb-0.5">${eventDate}</p>
                                <p class="text-[10px] text-gray-400 truncate mb-1.5">${eventVenue}</p>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-primary">80 RON</p>
                                    <button class="text-[10px] bg-primary text-white px-2 py-0.5 rounded-lg cursor-default font-semibold">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <!-- Other events (dimmed) -->
                        <div class="flex-shrink-0 w-36 bg-white rounded-xl shadow-sm overflow-hidden opacity-45 cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-2 bg-gray-100 rounded w-1/2"></div></div></div>
                        <div class="flex-shrink-0 w-36 bg-white rounded-xl shadow-sm overflow-hidden opacity-45 cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-2 bg-gray-100 rounded w-1/2"></div></div></div>
                        <div class="flex-shrink-0 w-36 bg-white rounded-xl shadow-sm overflow-hidden opacity-45 cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-2 bg-gray-100 rounded w-1/2"></div></div></div>
                        <div class="flex-shrink-0 w-36 bg-white rounded-xl shadow-sm overflow-hidden opacity-45 cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev4/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-2 bg-gray-100 rounded w-1/2"></div></div></div>
                    </div>
                </div>
                <!-- Rest of page (dimmed) -->
                <div class="p-4 bg-white mt-2 opacity-35">
                    <div class="h-3 bg-gray-200 rounded w-40 mb-3"></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev5/300/180" class="w-full h-16 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev6/300/180" class="w-full h-16 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev7/300/180" class="w-full h-16 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    category: {
        title: 'Pagina Categorie Eveniment',
        description: 'Evenimentul tau apare ca eveniment recomandat la inceputul paginii de categorie (ex: Concerte, Festivaluri). Ajunge la publicul targetat pe tipul de eveniment.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="bg-gray-100 text-sm select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="font-black text-primary text-base tracking-tight">ambilet</span>
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
                <div class="bg-white border-b border-gray-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
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
                <div class="p-4 ring-4 ring-primary ring-inset bg-white">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] font-black text-primary uppercase tracking-wider">★ Eveniment Promovat</span>
                        <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-1.5 py-0.5 rounded-full">Evenimentul tau</span>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg ring-2 ring-primary overflow-hidden relative cursor-pointer">
                        <div class="absolute top-2.5 right-2.5 z-10 bg-primary text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-md">★ PROMOVAT</div>
                        <div class="flex">
                            <img src="${eventImage}" alt="${eventName}" class="w-32 h-28 object-cover flex-shrink-0">
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
                                        <p class="font-black text-primary text-sm">80 RON</p>
                                    </div>
                                    <button class="bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-xl cursor-default shadow-sm">Cumpara Bilete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Other events (dimmed) -->
                <div class="p-4 opacity-35">
                    <p class="text-xs font-semibold text-gray-500 mb-3">Toate concertele</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-3 bg-primary/20 rounded w-1/2"></div></div></div>
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-3 bg-primary/20 rounded w-1/2"></div></div></div>
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev3/300/180" class="w-full h-20 object-cover"><div class="p-2"><div class="h-2.5 bg-gray-200 rounded mb-1"></div><div class="h-2 bg-gray-100 rounded w-2/3 mb-1"></div><div class="h-3 bg-primary/20 rounded w-1/2"></div></div></div>
                    </div>
                </div>
            </div>
        `
    },
    city: {
        title: 'Pagina Oras Eveniment',
        description: 'Evenimentul tau apare in sectiunea "Populare in [oras]" pe pagina dedicata orasului evenimentului. Ajunge la utilizatorii care cauta evenimente in zona lor.',
        buildContent: (eventName, eventImage, eventDate, eventVenue) => `
            <div class="bg-gray-100 text-sm select-none">
                <!-- Site Nav -->
                <div class="bg-white border-b border-gray-200 flex items-center gap-2 px-4 py-2.5 text-xs">
                    <span class="font-black text-primary text-base tracking-tight">ambilet</span>
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
                <div class="bg-gradient-to-r from-blue-600 to-cyan-500 px-5 py-4 text-white">
                    <div class="flex items-center gap-2 mb-0.5">
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="font-black text-lg">București</span>
                    </div>
                    <p class="text-blue-100 text-xs mb-3">Descopera cele mai bune evenimente din oras</p>
                    <div class="flex gap-2">
                        <span class="px-2.5 py-1 bg-white/25 text-white text-[11px] rounded-full font-medium cursor-pointer">Toate</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Concerte</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Weekend</span>
                        <span class="px-2.5 py-1 bg-white/10 text-white/80 text-[11px] rounded-full cursor-pointer">Gratuit</span>
                    </div>
                </div>
                <!-- FEATURED IN CITY — your event appears here -->
                <div class="bg-white px-4 pt-4 pb-4 ring-4 ring-blue-500 ring-inset">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔥</span>
                            <span class="font-bold text-gray-900">Populare in București</span>
                            <span class="bg-yellow-100 text-yellow-800 text-[10px] font-black px-1.5 py-0.5 rounded-full">Evenimentul tau</span>
                        </div>
                        <span class="text-xs text-blue-600 font-medium cursor-pointer">Toate →</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <!-- YOUR EVENT — highlighted, first position -->
                        <div class="col-span-2 bg-white rounded-2xl shadow-lg ring-2 ring-blue-500 overflow-hidden relative cursor-pointer">
                            <div class="absolute top-2 left-2 z-10 bg-yellow-400 text-yellow-900 text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">Evenimentul tau</div>
                            <div class="absolute top-2 right-2 z-10 bg-blue-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full shadow">★ PROMOVAT</div>
                            <img src="${eventImage}" alt="${eventName}" class="w-full h-28 object-cover">
                            <div class="p-3">
                                <p class="font-black text-gray-900 text-sm leading-tight mb-1">${eventName}</p>
                                <p class="text-[11px] text-gray-500 mb-0.5">${eventDate}</p>
                                <p class="text-[11px] text-gray-400 truncate mb-2">${eventVenue}</p>
                                <div class="flex items-center justify-between">
                                    <p class="font-black text-blue-600 text-sm">de la 80 RON</p>
                                    <button class="bg-blue-500 text-white text-[11px] font-bold px-3 py-1 rounded-lg cursor-default shadow-sm">Bilete</button>
                                </div>
                            </div>
                        </div>
                        <!-- Other city events (dimmed) -->
                        <div class="space-y-2 opacity-40">
                            <div class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev1/300/180" class="w-full h-16 object-cover"><div class="p-2"><div class="h-2 bg-gray-200 rounded mb-1"></div><div class="h-1.5 bg-gray-100 rounded w-2/3"></div></div></div>
                            <div class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer"><img src="https://picsum.photos/seed/ambilet-ev2/300/180" class="w-full h-16 object-cover"><div class="p-2"><div class="h-2 bg-gray-200 rounded mb-1"></div><div class="h-1.5 bg-gray-100 rounded w-2/3"></div></div></div>
                        </div>
                    </div>
                </div>
                <!-- More events in city (dimmed) -->
                <div class="p-4 bg-white mt-2 opacity-35">
                    <div class="h-3 bg-gray-200 rounded w-48 mb-3"></div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev3/200/150" class="w-full h-12 object-cover"><div class="p-1.5"><div class="h-2 bg-gray-200 rounded mb-1"></div></div></div>
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev4/200/150" class="w-full h-12 object-cover"><div class="p-1.5"><div class="h-2 bg-gray-200 rounded mb-1"></div></div></div>
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev5/200/150" class="w-full h-12 object-cover"><div class="p-1.5"><div class="h-2 bg-gray-200 rounded mb-1"></div></div></div>
                        <div class="bg-gray-50 rounded-xl overflow-hidden"><img src="https://picsum.photos/seed/ambilet-ev6/200/150" class="w-full h-12 object-cover"><div class="p-1.5"><div class="h-2 bg-gray-200 rounded mb-1"></div></div></div>
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
