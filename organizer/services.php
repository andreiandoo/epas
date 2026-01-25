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
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Servicii Extra</h1>
                    <p class="text-sm text-muted">Promoveaza-ti evenimentele si creste vanzarile</p>
                </div>
                <a href="/organizator/services/orders" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Comenzile mele
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span class="text-sm text-muted">Servicii Active</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="active-services">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Vizualizari Totale</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-views">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Emailuri Trimise</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="emails-sent">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Investit Total</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-spent">0 RON</p>
                </div>
            </div>

            <!-- Services Grid -->
            <h2 class="text-lg font-bold text-secondary mb-4">Servicii Disponibile</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Event Featuring -->
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-secondary mb-1">Promovare Eveniment</h3>
                                <p class="text-sm text-muted mb-4">Afiseaza evenimentul tau pe pagina principala, in categorii, genuri sau orase pentru vizibilitate maxima.</p>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Pagina Principala</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Categorii</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Genuri</span>
                                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">Orase</span>
                                </div>
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-primary">49 RON</span> / zi</p>
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
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
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
                                <p class="text-sm font-semibold text-secondary">De la <span class="text-accent">0.05 RON</span> / email</p>
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
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
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
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
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
                            <label class="cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="home" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <p class="font-medium text-secondary">Pagina Principala</p>
                                    <p class="text-sm text-muted">Vizibilitate maxima</p>
                                    <p class="text-sm font-semibold text-primary mt-2">99 RON / zi</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="category" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <p class="font-medium text-secondary">Pagina Categorie</p>
                                    <p class="text-sm text-muted">Audienta targetata</p>
                                    <p class="text-sm font-semibold text-primary mt-2">69 RON / zi</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="genre" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <p class="font-medium text-secondary">Pagina Gen</p>
                                    <p class="text-sm text-muted">Fani interesati</p>
                                    <p class="text-sm font-semibold text-primary mt-2">59 RON / zi</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="featuring_locations[]" value="city" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5">
                                    <p class="font-medium text-secondary">Pagina Oras</p>
                                    <p class="text-sm text-muted">Audienta locala</p>
                                    <p class="text-sm font-semibold text-primary mt-2">49 RON / zi</p>
                                </div>
                            </label>
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
                        <label class="label">Audienta Email</label>
                        <div class="space-y-3">
                            <label class="cursor-pointer block">
                                <input type="radio" name="email_audience" value="all" class="peer sr-only" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-secondary">Baza Completa</p>
                                            <p class="text-sm text-muted">Trimite catre toti utilizatorii activi</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-all-count">~250,000</p>
                                            <p class="text-xs text-muted">utilizatori</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="cursor-pointer block">
                                <input type="radio" name="email_audience" value="filtered" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-secondary">Audienta Filtrata</p>
                                            <p class="text-sm text-muted">Utilizatori din orasul/categoria evenimentului</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-filtered-count">~45,000</p>
                                            <p class="text-xs text-muted">utilizatori</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <label class="cursor-pointer block">
                                <input type="radio" name="email_audience" value="own" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-accent peer-checked:bg-accent/5">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-secondary">Clientii Tai</p>
                                            <p class="text-sm text-muted">Doar participantii de la evenimentele tale anterioare</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-accent" id="audience-own-count">0</p>
                                            <p class="text-xs text-muted">clienti</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="bg-surface rounded-xl p-4">
                            <p class="text-sm text-muted mb-2">Cost per email: <span class="font-semibold text-secondary">0.05 RON</span></p>
                            <p class="text-sm text-muted">Cost estimat: <span class="font-bold text-accent" id="email-cost-estimate">12,500 RON</span></p>
                        </div>
                        <div>
                            <label class="label">Data Trimitere *</label>
                            <input type="datetime-local" id="email-send-date" class="input w-full" required>
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
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="card" class="peer sr-only" checked>
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5 flex items-center gap-3">
                                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    <span class="font-medium text-secondary">Card Bancar</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="transfer" class="peer sr-only">
                                <div class="p-4 border-2 border-border rounded-xl peer-checked:border-primary peer-checked:bg-primary/5 flex items-center gap-3">
                                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                    <span class="font-medium text-secondary">Transfer Bancar</span>
                                </div>
                            </label>
                        </div>
                        <div id="card-payment-fields">
                            <p class="text-sm text-muted">Vei fi redirectionat catre procesatorul de plati pentru a finaliza tranzactia in siguranta.</p>
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

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

let currentStep = 1;
let currentServiceType = '';
let events = [];
let activeServices = [];

document.addEventListener('DOMContentLoaded', function() {
    loadEvents();
    loadActiveServices();
    loadStats();
    setupPaymentMethodToggle();
    setupEmailCostCalculation();
});

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events');
        if (response.success && response.data.events) {
            events = response.data.events;
            const select = document.getElementById('service-event');
            events.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.title;
                opt.dataset.image = e.image;
                opt.dataset.date = e.date;
                opt.dataset.venue = e.venue;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.log('Events will load when API is available');
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
            document.getElementById('event-image').src = event.image || '/assets/images/default-event.png';
            document.getElementById('event-title').textContent = event.title;
            document.getElementById('event-date').textContent = AmbiletUtils.formatDate(event.date);
            document.getElementById('event-venue').textContent = event.venue || '';
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
            break;
        case 'email':
            // Email options are always valid (has default selection)
            break;
        case 'tracking':
            const platforms = document.querySelectorAll('input[name="tracking_platforms[]"]:checked');
            if (!platforms.length) {
                AmbiletNotifications.error('Selecteaza cel putin o platforma');
                return false;
            }
            break;
        case 'campaign':
            // Campaign options are always valid (has default selection)
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

            const prices = { home: 99, category: 69, genre: 59, city: 49 };
            const labels = { home: 'Pagina Principala', category: 'Pagina Categorie', genre: 'Pagina Gen', city: 'Pagina Oras' };

            locations.forEach(loc => {
                const price = prices[loc.value] * days;
                items.push({ name: labels[loc.value] + ' (' + days + ' zile)', price });
                total += price;
            });
            break;

        case 'email':
            const audience = document.querySelector('input[name="email_audience"]:checked').value;
            const counts = { all: 250000, filtered: 45000, own: 0 };
            const audienceLabels = { all: 'Baza Completa', filtered: 'Audienta Filtrata', own: 'Clientii Tai' };
            const emailCount = counts[audience];
            const emailPrice = emailCount * 0.05;
            items.push({ name: 'Campanie Email - ' + audienceLabels[audience] + ' (~' + AmbiletUtils.formatNumber(emailCount) + ' emailuri)', price: emailPrice });
            total = emailPrice;
            break;

        case 'tracking':
            const trackingPlatforms = document.querySelectorAll('input[name="tracking_platforms[]"]:checked');
            const duration = parseInt(document.getElementById('tracking-duration').value);
            const discounts = { 1: 0, 3: 0.1, 6: 0.15, 12: 0.25 };
            const platformPrice = 49;

            trackingPlatforms.forEach(p => {
                const platformLabels = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
                let price = platformPrice * duration * (1 - discounts[duration]);
                items.push({ name: platformLabels[p.value] + ' (' + duration + ' luni)', price });
                total += price;
            });
            break;

        case 'campaign':
            const campaignType = document.querySelector('input[name="campaign_type"]:checked').value;
            const campaignPrices = { basic: 499, standard: 899, premium: 1499 };
            const campaignLabels = { basic: 'Campanie Basic', standard: 'Campanie Standard', premium: 'Campanie Premium' };
            items.push({ name: campaignLabels[campaignType], price: campaignPrices[campaignType] });
            total = campaignPrices[campaignType];
            break;
    }

    summary.innerHTML = `
        <div class="flex justify-between text-sm">
            <span class="text-muted">Eveniment:</span>
            <span class="font-medium text-secondary">${event ? event.title : ''}</span>
        </div>
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

function setupEmailCostCalculation() {
    document.querySelectorAll('input[name="email_audience"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const counts = { all: 250000, filtered: 45000, own: 0 };
            const cost = counts[this.value] * 0.05;
            document.getElementById('email-cost-estimate').textContent = AmbiletUtils.formatCurrency(cost);
        });
    });
}

document.getElementById('service-event').addEventListener('change', function() {
    const event = events.find(e => e.id == this.value);
    if (event) {
        document.getElementById('event-preview').classList.remove('hidden');
        document.getElementById('event-image').src = event.image || '/assets/images/default-event.png';
        document.getElementById('event-title').textContent = event.title;
        document.getElementById('event-date').textContent = AmbiletUtils.formatDate(event.date);
        document.getElementById('event-venue').textContent = event.venue || '';
    } else {
        document.getElementById('event-preview').classList.add('hidden');
    }
});

document.getElementById('service-form').addEventListener('submit', async function(e) {
    e.preventDefault();

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
            data.audience = document.querySelector('input[name="email_audience"]:checked').value;
            data.send_date = document.getElementById('email-send-date').value;
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
        const response = await AmbiletAPI.post('/organizer/services/orders', data);
        if (response.success) {
            if (paymentMethod === 'card' && response.data.payment_url) {
                window.location.href = response.data.payment_url;
            } else {
                AmbiletNotifications.success('Comanda a fost inregistrata! Vei primi un email cu instructiunile de plata.');
                closeServiceModal();
                loadActiveServices();
            }
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la procesarea comenzii');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la procesarea comenzii');
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
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
