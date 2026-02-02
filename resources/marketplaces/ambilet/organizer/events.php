<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Evenimente';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js"></script>
HTML;
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- ============================================================ -->
            <!-- EVENTS LIST VIEW -->
            <!-- ============================================================ -->
            <div id="events-view">
                <!-- Page Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-secondary">Evenimentele mele</h1>
                        <p class="text-sm text-muted">Gestioneaza si monitorizeaza evenimentele tale</p>
                    </div>
                    <button onclick="showCreateForm()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Eveniment nou</button>
                </div>

                <div class="flex flex-wrap items-center gap-4 mb-6">
                    <div class="flex-1 min-w-[200px]"><input type="text" placeholder="Cauta evenimente..." class="w-full input" id="search-input"></div>
                    <select class="w-auto input" id="status-filter"><option value="">Toate statusurile</option><option value="published">Publicate</option><option value="draft">Ciorne</option><option value="ended">Incheiate</option></select>
                    <select class="w-auto input" id="sort-filter"><option value="date_desc">Cele mai recente</option><option value="date_asc">Cele mai vechi</option><option value="sales_desc">Cele mai vandute</option></select>
                </div>

                <div id="events-list" class="space-y-4"><div class="p-6 bg-white border animate-pulse rounded-2xl border-border"><div class="flex gap-6"><div class="w-32 h-24 rounded-lg bg-surface"></div><div class="flex-1 space-y-3"><div class="w-1/3 h-5 rounded bg-surface"></div><div class="w-1/4 h-4 rounded bg-surface"></div></div></div></div></div>

                <div id="no-events" class="hidden py-16 text-center bg-white border rounded-2xl border-border">
                    <div class="flex items-center justify-center w-24 h-24 mx-auto mb-6 rounded-full bg-muted/10"><svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                    <h2 class="mb-2 text-xl font-bold text-secondary">Nu ai evenimente inca</h2>
                    <p class="mb-6 text-muted">Creeaza primul tau eveniment si incepe sa vinzi bilete!</p>
                    <button onclick="showCreateForm()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Creeaza eveniment</button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- CREATE EVENT ACCORDION FORM -->
            <!-- ============================================================ -->
            <div id="create-event-view" class="hidden">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <button onclick="hideCreateForm()" class="p-2 transition-colors rounded-lg hover:bg-white text-muted hover:text-secondary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-secondary">Eveniment nou</h1>
                            <p class="text-sm text-muted">Completeaza informatiile pas cu pas</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="save-status" class="hidden text-sm text-muted"></span>
                        <button onclick="saveEventDraft()" class="btn btn-primary" id="save-draft-btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span id="save-btn-text">Salveaza ciorna</span>
                            <div id="save-btn-spinner" class="hidden spinner"></div>
                        </button>
                    </div>
                </div>

                <!-- Accordion Form -->
                <form id="create-event-form" class="space-y-3">
                    <!-- Hidden fields -->
                    <input type="hidden" id="saved-event-id" value="">
                    <input type="hidden" id="selected-event-type-ids" value="">
                    <input type="hidden" id="selected-venue-id" value="">

                    <!-- ============ EVENT STATUS ACTIONS (Edit mode only) ============ -->
                    <div id="event-status-actions" class="hidden p-5 bg-white border rounded-2xl border-border">
                        <h3 class="mb-4 font-semibold text-secondary">Acțiuni eveniment</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" onclick="toggleSoldOut()" id="btn-sold-out" class="btn btn-sm btn-secondary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Sold Out</span>
                            </button>
                            <button type="button" onclick="toggleDoorSales()" id="btn-door-sales" class="btn btn-sm btn-secondary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <span>Door Sales Only</span>
                            </button>
                            <button type="button" onclick="showPostponedModal()" id="btn-postponed" class="btn btn-sm btn-warning">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Amânat</span>
                            </button>
                            <button type="button" onclick="showCancelledModal()" id="btn-cancelled" class="btn btn-sm btn-error">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Anulat</span>
                            </button>
                        </div>
                        <!-- Status indicators -->
                        <div id="status-indicators" class="flex flex-wrap gap-2 mt-3"></div>
                    </div>

                    <!-- ============ STEP 1: Detalii Eveniment ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="1">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(1)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full step-indicator bg-primary">1</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Detalii eveniment</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-1"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="px-5 pb-5 accordion-body" id="accordion-body-1">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <div>
                                    <label class="label">Numele evenimentului <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required class="input" placeholder="ex: Concert Rock in Parc">
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="label">Categorie</label>
                                        <select name="marketplace_event_category_id" class="input" id="category-select" onchange="onCategoryChange(this.value)">
                                            <option value="">Selecteaza categoria</option>
                                        </select>
                                    </div>
                                    <div id="genres-container" class="hidden">
                                        <label class="label">Gen eveniment</label>
                                        <div class="multiselect-wrapper" id="genres-multiselect">
                                            <div class="multiselect-tags" id="genres-selected"></div>
                                            <input type="text" class="multiselect-input" placeholder="Cauta genuri..." id="genres-search-input" autocomplete="off">
                                            <div class="hidden multiselect-dropdown" id="genres-dropdown"></div>
                                        </div>
                                        <p class="mt-1 text-xs text-muted">Selecteaza genurile aplicabile</p>
                                    </div>
                                </div>
                                <!-- Artist Selection -->
                                <div>
                                    <label class="label">Artisti</label>
                                    <div class="multiselect-wrapper" id="artists-multiselect">
                                        <div class="multiselect-tags" id="artists-selected"></div>
                                        <input type="text" class="multiselect-input" placeholder="Cauta artisti..." id="artists-search-input" autocomplete="off">
                                        <div class="hidden multiselect-dropdown" id="artists-dropdown"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">Cauta in biblioteca sau scrie un nume nou pentru a-l adauga</p>
                                </div>
                                <div>
                                    <label class="label">Descriere scurta</label>
                                    <textarea name="short_description" rows="3" class="input" placeholder="O scurta descriere a evenimentului (max 120 cuvinte)" id="short-desc-input"></textarea>
                                    <p class="mt-1 text-xs text-muted"><span id="short-desc-count">0</span>/120 cuvinte</p>
                                </div>
                                <div>
                                    <label class="label">Etichete</label>
                                    <input type="text" name="tags" class="input" placeholder="rock, live, outdoor (separate cu virgula)">
                                    <p class="mt-1 text-xs text-muted">Separa etichetele cu virgula</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 2: Program ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="2">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(2)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">2</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Program</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-2"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-2">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <!-- Duration Mode Selector -->
                                <div>
                                    <label class="label">Tipul duratei <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 gap-3" id="duration-mode-selector">
                                        <label class="flex items-center gap-3 p-3 transition-colors border-2 border-gray-200 cursor-pointer duration-mode-option rounded-xl hover:border-primary/50">
                                            <input type="radio" name="duration_mode" value="single_day" class="accent-primary" onchange="onDurationModeChange('single_day')">
                                            <div>
                                                <span class="text-sm font-medium text-secondary">O singura zi</span>
                                                <p class="text-xs text-muted">Evenimentul are loc intr-o singura zi</p>
                                            </div>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 transition-colors border-2 border-gray-200 cursor-pointer duration-mode-option rounded-xl hover:border-primary/50">
                                            <input type="radio" name="duration_mode" value="range" class="accent-primary" onchange="onDurationModeChange('range')">
                                            <div>
                                                <span class="text-sm font-medium text-secondary">Interval de zile</span>
                                                <p class="text-xs text-muted">Evenimentul se intinde pe mai multe zile</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Date/Time fields - hidden until duration mode selected -->
                                <div id="schedule-fields" class="hidden space-y-4">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="label">Data eveniment <span class="text-red-500">*</span></label>
                                            <input type="date" name="start_date" required class="input">
                                        </div>
                                        <div>
                                            <label class="label">Ora incepere <span class="text-red-500">*</span></label>
                                            <input type="time" name="start_time" required class="input">
                                        </div>
                                    </div>
                                    <div id="end-date-fields" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="label">Data sfarsit <span class="text-red-500">*</span></label>
                                                <input type="date" name="end_date" class="input">
                                            </div>
                                            <div>
                                                <label class="label">Ora sfarsit</label>
                                                <input type="time" name="end_time" class="input">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="single-end-time" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="label">Ora sfarsit</label>
                                                <input type="time" name="end_time_single" class="input">
                                            </div>
                                            <div>
                                                <label class="label">Ora deschidere usi</label>
                                                <input type="time" name="door_time" class="input">
                                                <p class="mt-1 text-xs text-muted">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="range-door-time" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="label">Ora deschidere usi</label>
                                                <input type="time" name="door_time_range" class="input">
                                                <p class="mt-1 text-xs text-muted">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="duration-mode-hint" class="text-sm italic text-muted">
                                    Selecteaza tipul duratei pentru a configura programul.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 3: Locatie ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="3">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(3)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">3</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Locatie</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-3"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-3">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <div>
                                    <label class="label">Nume locatie / sala <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="text" name="venue_name" required class="input" placeholder="Cauta sau scrie numele locatiei..." id="venue-search-input" autocomplete="off">
                                        <div id="venue-dropdown" class="absolute z-50 hidden w-full mt-1 overflow-y-auto bg-white border border-gray-200 shadow-lg rounded-xl max-h-60">
                                            <!-- Populated dynamically -->
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">Cauta in biblioteca de locatii sau scrie manual</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="label">Oras <span class="text-red-500">*</span></label>
                                        <input type="text" name="venue_city" required class="input" placeholder="ex: Bucuresti" id="venue-city-input">
                                    </div>
                                    <div>
                                        <label class="label">Adresa</label>
                                        <input type="text" name="venue_address" class="input" placeholder="ex: Str. Lipscani nr. 10" id="venue-address-input">
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="label">Website eveniment</label>
                                        <input type="url" name="website_url" class="input" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label class="label">Link Facebook</label>
                                        <input type="url" name="facebook_url" class="input" placeholder="https://facebook.com/events/...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 4: Continut ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="4">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(4)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">4</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Continut & Descriere</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-4"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-4">
                            <div class="pt-2 space-y-6 border-t border-gray-100">
                                <div>
                                    <label class="label">Descriere completa</label>
                                    <textarea name="description" id="description-editor"></textarea>
                                    <p class="mt-1 text-xs text-muted">Descrie evenimentul in detaliu: lineup, program, reguli de acces, etc.</p>
                                </div>
                                <div>
                                    <label class="label">Conditii eveniment</label>
                                    <textarea name="ticket_terms" id="ticket-terms-editor"></textarea>
                                    <p class="mt-1 text-xs text-muted">Conditii de participare, restrictii de varsta, reguli speciale, politica de retur, etc.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 5: Media ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="5">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(5)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">5</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Media</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-5"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-5">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <label class="label">Poster (vertical)</label>
                                        <div class="relative">
                                            <div id="poster-preview" class="hidden mb-3">
                                                <img id="poster-img" src="" alt="Poster" class="w-full max-w-[200px] rounded-xl border border-border">
                                                <button type="button" onclick="removePoster()" class="absolute p-1 text-white bg-red-500 rounded-full top-2 right-2 hover:bg-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="flex flex-col items-center justify-center w-full h-32 transition-colors border-2 border-gray-300 border-dashed cursor-pointer drop-zone rounded-xl hover:border-primary hover:bg-primary/5" id="poster-upload-area" data-target="poster">
                                                <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-muted">Trage imaginea aici sau click pentru upload</span>
                                                <span class="mt-1 text-xs text-muted">JPG, PNG (recomandat 800x1200, max 5MB)</span>
                                                <input type="file" name="poster" accept="image/*" class="hidden" onchange="previewPoster(this)">
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label">Imagine cover (orizontala)</label>
                                        <div class="relative">
                                            <div id="cover-preview" class="hidden mb-3">
                                                <img id="cover-img" src="" alt="Cover" class="w-full border rounded-xl border-border">
                                                <button type="button" onclick="removeCover()" class="absolute p-1 text-white bg-red-500 rounded-full top-2 right-2 hover:bg-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="flex flex-col items-center justify-center w-full h-32 transition-colors border-2 border-gray-300 border-dashed cursor-pointer drop-zone rounded-xl hover:border-primary hover:bg-primary/5" id="cover-upload-area" data-target="cover">
                                                <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-muted">Trage imaginea aici sau click pentru upload</span>
                                                <span class="mt-1 text-xs text-muted">JPG, PNG (recomandat 1200x630, max 5MB)</span>
                                                <input type="file" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 6: Bilete ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="6">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(6)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">6</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Bilete</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-6"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-6">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <p class="text-sm text-muted">Adauga cel putin un tip de bilet. Poti adauga mai multe categorii (ex: Early Bird, Standard, VIP).</p>

                                <div id="ticket-types-container" class="space-y-4">
                                    <!-- First ticket type (default) -->
                                    <div class="p-4 border border-gray-200 ticket-type-item rounded-xl" data-index="0">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-sm font-semibold text-secondary">Tip bilet #1</h4>
                                            <button type="button" onclick="removeTicketType(this)" class="hidden text-red-400 hover:text-red-600 remove-ticket-btn">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div>
                                                <label class="text-xs label">Nume bilet <span class="text-red-500">*</span></label>
                                                <input type="text" name="ticket_name_0" required class="input" placeholder="ex: Standard, VIP, Early Bird">
                                            </div>
                                            <div>
                                                <label class="text-xs label">Pret (RON) <span class="text-red-500">*</span></label>
                                                <input type="number" name="ticket_price_0" required class="input" placeholder="0.00" step="0.01" min="0">
                                            </div>
                                            <div>
                                                <label class="text-xs label">Stoc bilete</label>
                                                <input type="number" name="ticket_quantity_0" class="input" placeholder="Nelimitat" min="1">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-xs label">Descriere bilet</label>
                                            <input type="text" name="ticket_desc_0" class="input" placeholder="ex: Acces general, loc nenumerotat">
                                        </div>
                                        <div class="grid gap-3 mt-3 md:grid-cols-2">
                                            <div>
                                                <label class="text-xs label">Min. bilete/comanda</label>
                                                <input type="number" name="ticket_min_0" class="input" placeholder="1" min="1">
                                            </div>
                                            <div>
                                                <label class="text-xs label">Max. bilete/comanda</label>
                                                <input type="number" name="ticket_max_0" class="input" placeholder="10" min="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" onclick="addTicketType()" class="flex items-center justify-center w-full gap-2 py-3 text-sm font-medium transition-colors border-2 border-gray-300 border-dashed rounded-xl text-muted hover:border-primary hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Adauga alt tip de bilet
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 7: Setari vanzari ============ -->
                    <div class="overflow-hidden bg-white border accordion-section rounded-2xl border-border" data-step="7">
                        <button type="button" class="flex items-center justify-between w-full p-5 text-left transition-colors accordion-header hover:bg-gray-50" onclick="toggleAccordion(7)">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-8 h-8 text-sm font-bold text-gray-600 bg-gray-200 rounded-full step-indicator">7</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Setari vanzari</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-7"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 transition-transform text-muted accordion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden px-5 pb-5 accordion-body" id="accordion-body-7">
                            <div class="pt-2 space-y-4 border-t border-gray-100">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="label">Capacitate totala</label>
                                        <input type="number" name="capacity" class="input" placeholder="ex: 500" min="1">
                                        <p class="mt-1 text-xs text-muted">Numarul total de locuri disponibile</p>
                                    </div>
                                    <div>
                                        <label class="label">Max. bilete per comanda</label>
                                        <input type="number" name="max_tickets_per_order" class="input" placeholder="10" min="1" max="50">
                                        <p class="mt-1 text-xs text-muted">Cate bilete poate cumpara un client intr-o comanda</p>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="label">Inceput vanzari</label>
                                        <input type="datetime-local" name="sales_start_at" class="input">
                                        <p class="mt-1 text-xs text-muted">Cand incep vanzarile (gol = imediat)</p>
                                    </div>
                                    <div>
                                        <label class="label">Sfarsit vanzari</label>
                                        <input type="datetime-local" name="sales_end_at" class="input">
                                        <p class="mt-1 text-xs text-muted">Cand se opresc vanzarile (gol = la inceput eveniment)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Actions -->
                    <div class="flex items-center justify-between pt-4">
                        <button type="button" onclick="hideCreateForm()" class="btn btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Inapoi la evenimente
                        </button>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="saveEventDraft()" class="btn btn-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Salveaza ciorna
                            </button>
                            <button type="button" onclick="saveAndSubmitEvent()" class="btn btn-success" id="submit-review-btn">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Salveaza si trimite spre aprobare
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Postponed Modal -->
    <div id="postponed-modal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50">
        <div class="w-full max-w-md p-6 mx-4 bg-white rounded-2xl">
            <h3 class="mb-4 text-lg font-bold text-secondary">Amână evenimentul</h3>
            <form id="postponed-form" onsubmit="savePostponed(event)" class="space-y-4">
                <div>
                    <label class="label">Noua dată a evenimentului</label>
                    <input type="date" name="postponed_date" class="input" required>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label">Ora start</label>
                        <input type="time" name="postponed_start_time" class="input">
                    </div>
                    <div>
                        <label class="label">Ora deschidere</label>
                        <input type="time" name="postponed_door_time" class="input">
                    </div>
                    <div>
                        <label class="label">Ora sfârșit</label>
                        <input type="time" name="postponed_end_time" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">Motivul amânării</label>
                    <textarea name="postponed_reason" class="input" rows="3" placeholder="Ex: Din motive tehnice, evenimentul a fost amânat..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closePostponedModal()" class="btn btn-secondary">Anulează</button>
                    <button type="submit" class="btn btn-warning">Marchează ca amânat</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancelled Modal -->
    <div id="cancelled-modal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50">
        <div class="w-full max-w-md p-6 mx-4 bg-white rounded-2xl">
            <h3 class="mb-4 text-lg font-bold text-secondary">Anulează evenimentul</h3>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-50 rounded-xl">
                <strong>Atenție!</strong> Anularea evenimentului va notifica toți participanții și nu poate fi anulată.
            </div>
            <form id="cancelled-form" onsubmit="saveCancelled(event)" class="space-y-4">
                <div>
                    <label class="label">Motivul anulării <span class="text-red-500">*</span></label>
                    <textarea name="cancel_reason" class="input" rows="3" placeholder="Ex: Din cauza condițiilor meteo, evenimentul a fost anulat..." required></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeCancelledModal()" class="btn btn-secondary">Înapoi</button>
                    <button type="submit" class="btn btn-error">Confirmă anularea</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .accordion-body.hidden { display: none; }
        .accordion-section[data-open="true"] .accordion-chevron { transform: rotate(180deg); }
        .accordion-section[data-open="true"] .step-indicator { background-color: var(--color-primary, #6366f1); color: white; }
        .step-indicator.completed { background-color: #10b981 !important; color: white !important; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .duration-mode-option:has(input:checked) { border-color: var(--color-primary, #6366f1); background-color: rgba(99, 102, 241, 0.05); }
        .multiselect-wrapper { position: relative; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 6px 10px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; min-height: 42px; background: white; cursor: text; }
        .multiselect-wrapper:focus-within { border-color: var(--color-primary, #6366f1); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
        .multiselect-tags { display: contents; }
        .multiselect-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: var(--color-primary, #6366f1); color: white; white-space: nowrap; }
        .multiselect-tag button { background: none; border: none; color: white; cursor: pointer; font-size: 14px; line-height: 1; padding: 0 2px; opacity: 0.7; }
        .multiselect-tag button:hover { opacity: 1; }
        .multiselect-input { border: none; outline: none; flex: 1; min-width: 80px; font-size: 0.875rem; padding: 2px 4px; background: transparent; }
        .multiselect-dropdown { position: absolute; z-index: 50; left: 0; right: 0; top: 100%; margin-top: 4px; background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; }
        .multiselect-option { padding: 8px 12px; cursor: pointer; font-size: 0.875rem; transition: background 0.1s; }
        .multiselect-option:hover { background-color: #f3f4f6; }
        .multiselect-option.create-new { color: var(--color-primary, #6366f1); font-weight: 500; border-top: 1px solid #e5e7eb; }
        .venue-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: background 0.1s; }
        .venue-option:hover { background-color: #f9fafb; }
        .venue-option.bg-amber-50 { background-color: #fffbeb; }
        .venue-option.bg-amber-50:hover { background-color: #fef3c7; }
        .venue-option:last-child { border-bottom: none; }
        .tox-tinymce { border-radius: 0.75rem !important; border-color: #e5e7eb !important; }
        .tox .tox-edit-area__iframe { min-height: 160px; }
    </style>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

let ticketTypeCount = 1;
let descriptionEditor = null;
let ticketTermsEditor = null;
let categoriesData = [];
let venueSearchTimeout = null;
let artistSearchTimeout = null;
let selectedGenres = []; // [{id, name}]
let selectedArtists = []; // [{id, name}]
let availableGenres = []; // full list from API

// Check if we should show create form or edit on load
const urlParams = new URLSearchParams(window.location.search);
// Extract event ID from path (e.g., /organizator/event/4) or query string
const pathMatch = window.location.pathname.match(/\/organizator\/event\/(\d+)/);
const eventIdFromPath = pathMatch ? pathMatch[1] : null;
const eventIdFromQuery = urlParams.get('id');
const eventId = eventIdFromPath || eventIdFromQuery;

if (urlParams.get('action') === 'create') {
    showCreateForm();
} else if (eventId) {
    // If we have an event ID (from path or query string), load for editing
    loadEventForEdit(eventId);
} else {
    loadEvents();
}

// ==================== EVENTS LIST ====================

let allEventsCache = []; // Cache for instant search

async function loadEvents() {
    try {
        const response = await AmbiletAPI.organizer.getEvents();
        const events = response.data || response || [];
        allEventsCache = events; // Cache for instant filtering

        // Update sidebar events count
        const activeEvents = events.filter(e => e.status === 'published' || e.status === 'active').length;
        const navCount = document.getElementById('nav-events-count');
        if (navCount) navCount.textContent = activeEvents || events.length;

        // Apply current search filter if any
        const searchQuery = document.getElementById('search-input')?.value?.toLowerCase() || '';
        const filteredEvents = filterEvents(events, searchQuery);

        if (filteredEvents.length === 0) {
            document.getElementById('events-list').classList.add('hidden');
            document.getElementById('no-events').classList.remove('hidden');
        } else {
            document.getElementById('events-list').classList.remove('hidden');
            document.getElementById('no-events').classList.add('hidden');
            renderEvents(filteredEvents);
        }
    } catch (error) {
        document.getElementById('events-list').classList.add('hidden');
        document.getElementById('no-events').classList.remove('hidden');
    }
}

function filterEvents(events, query) {
    if (!query) return events;
    return events.filter(event => {
        const name = (event.name || event.title || '').toLowerCase();
        const venue = (event.venue_name || '').toLowerCase();
        const city = (event.venue_city || '').toLowerCase();
        return name.includes(query) || venue.includes(query) || city.includes(query);
    });
}

function instantSearchEvents() {
    const query = document.getElementById('search-input')?.value?.toLowerCase() || '';
    const filteredEvents = filterEvents(allEventsCache, query);

    if (filteredEvents.length === 0) {
        document.getElementById('events-list').classList.add('hidden');
        document.getElementById('no-events').classList.remove('hidden');
    } else {
        document.getElementById('events-list').classList.remove('hidden');
        document.getElementById('no-events').classList.add('hidden');
        renderEvents(filteredEvents);
    }
}

// Initialize instant search
document.getElementById('search-input')?.addEventListener('input', AmbiletUtils.debounce(instantSearchEvents, 150));

function renderEvents(events) {
    const container = document.getElementById('events-list');
    const statusColors = { published: 'success', draft: 'warning', ended: 'muted', pending_review: 'info', cancelled: 'error', postponed: 'warning', sold_out: 'info' };
    const statusLabels = { published: 'Publicat', draft: 'Ciornă', ended: 'Încheiat', pending_review: 'În așteptare', cancelled: 'Anulat', postponed: 'Amânat', sold_out: 'Sold Out' };

    container.innerHTML = events.map(event => {
        // Determine if event has ended
        const eventEndDate = event.ends_at || event.starts_at;
        const isEnded = event.status === 'ended' || event.is_past || event.is_ended ||
            (eventEndDate && new Date(eventEndDate) < new Date());

        // Determine if event is ongoing (published and not ended)
        const isOngoing = event.status === 'published' && !isEnded && !event.is_cancelled && !event.is_postponed;

        // Determine display status
        let displayStatus = event.status;
        let saleStatus = '';
        if (event.is_cancelled || event.status === 'cancelled') displayStatus = 'cancelled';
        else if (event.is_postponed || event.status === 'postponed') displayStatus = 'postponed';
        else if (event.is_sold_out) { displayStatus = 'published'; saleStatus = 'Sold Out'; }
        else if (event.is_door_sales_only) { displayStatus = 'published'; saleStatus = 'Door Sales'; }
        else if (isEnded) displayStatus = 'ended';
        else if (isOngoing) saleStatus = 'În vânzare';

        // Days until event
        const daysUntil = event.days_until;
        let daysText = '';
        if (daysUntil !== null && daysUntil !== undefined && !isEnded) {
            if (daysUntil === 0) daysText = 'Azi';
            else if (daysUntil === 1) daysText = 'Mâine';
            else if (daysUntil > 0) daysText = `${daysUntil} zile`;
        }

        // Generate Analytics/Report button
        const analyticsButton = isEnded
            ? `<a href="/organizator/report/${event.id}" class="btn btn-sm btn-secondary" title="Raport"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></a>`
            : `<a href="/organizator/analytics/${event.id}" class="btn btn-sm btn-secondary" title="Analytics"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></a>`;

        // Promote button (only for ongoing events)
        const promoteButton = isOngoing
            ? `<a href="/organizator/servicii?event=${event.id}" class="btn btn-sm btn-primary" title="Promovează"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>Promovează</a>`
            : '';

        // Documents button
        const documentsButton = `<a href="/organizator/documente?event=${event.id}" class="btn btn-sm btn-secondary" title="Documente"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></a>`;

        // View/Preview button - use /bilete/{slug} for published, add ?preview=1 for drafts
        const isPublishedEvent = event.status === 'published' || event.is_public;
        const viewUrl = isPublishedEvent ? `/bilete/${event.slug}` : `/bilete/${event.slug}?preview=1`;
        const viewButton = `<a href="${viewUrl}" target="_blank" class="btn btn-sm btn-secondary" title="${isPublishedEvent ? 'Vizualizează' : 'Previzualizare'}"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>`;

        return `
        <div class="transition-colors bg-white border rounded-2xl border-border hover:border-primary/30">
            <div class="flex flex-col md:items-center md:flex-row">
                <img src="${getStorageUrl(event.image)}" alt="${event.name || event.title}" class="object-cover w-full h-40 rounded-tr-none rounded-br-none md:w-40 rounded-xl" loading="lazy">
                <div class="flex-1 py-2">
                    <div class="flex items-center justify-between gap-4 pr-4">
                        <div class="flex items-center gap-4 pl-6">
                            <h3 class="mb-1 text-lg font-bold text-secondary">${event.name || event.title}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted">
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${event.starts_at ? AmbiletUtils.formatDate(event.starts_at) : (event.start_date ? AmbiletUtils.formatDate(event.start_date) : '')}</span>
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>${[event.venue_name, event.venue_city].filter(Boolean).join(', ') || ''}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-${statusColors[displayStatus] || 'secondary'}">${statusLabels[displayStatus] || displayStatus}</span>
                                ${saleStatus ? `<span class="badge ${saleStatus === 'În vânzare' ? 'badge-success' : (saleStatus === 'Sold Out' ? 'badge-info' : 'badge-warning')}">${saleStatus}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2 pr-4">
                            ${event.is_editable !== false ? `<a href="/organizator/event/${event.id}?action=edit" class="btn btn-sm btn-secondary" title="Editează"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>` : ''}
                            ${documentsButton}
                            ${analyticsButton}
                            ${promoteButton}
                            ${viewButton}
                            ${['draft', 'rejected'].includes(event.status) ? `<button onclick="deleteEvent(${event.id}, '${(event.name || event.title).replace(/'/g, "\\'")}');" class="btn btn-sm btn-error" title="Șterge evenimentul"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>` : ''}
                        </div>
                    </div>
                    <div class="grid grid-cols-5 gap-4 pt-4 mt-4 border-t border-border pl-6">
                        <div><p class="text-2xl font-bold text-secondary">${event.tickets_sold || 0}</p><p class="text-xs text-muted">Bilete vândute</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${AmbiletUtils.formatCurrency(event.revenue || 0)}</p><p class="text-xs text-muted">Încasări</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${event.effective_commission_rate || 5}%</p><p class="text-xs text-muted">${event.use_fixed_commission ? 'Comision fix' : 'Comision'}</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${event.views || 0}</p><p class="text-xs text-muted">Vizualizări</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${daysText || '-'}</p><p class="text-xs text-muted">${isEnded ? 'Încheiat' : 'Până la event'}</p></div>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}

async function deleteEvent(eventId, eventName) {
    if (!confirm(`Esti sigur ca vrei sa stergi evenimentul "${eventName}"?\n\nAceasta actiune este ireversibila.`)) {
        return;
    }

    try {
        AmbiletNotifications.info('Se sterge evenimentul...');
        const response = await AmbiletAPI.delete(`/organizer/events/${eventId}`);

        if (response.success) {
            AmbiletNotifications.success('Evenimentul a fost sters cu succes');
            loadEvents(); // Reload the events list
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la stergerea evenimentului');
        }
    } catch (error) {
        console.error('Delete event error:', error);
        AmbiletNotifications.error(error.message || 'Eroare la stergerea evenimentului');
    }
}

// ==================== CREATE FORM ====================

function showCreateForm() {
    document.getElementById('events-view').classList.add('hidden');
    document.getElementById('create-event-view').classList.remove('hidden');
    history.pushState({}, '', '/organizator/events?action=create');
    toggleAccordion(1);
    loadCategories();
    initEditors();
    initVenueSearch();
    initArtistSearch();
    initGenreSearch();
    initDragDrop();
    initShortDescWordCount();

    // Reset form state for new event
    resetFormState();
}

function resetFormState() {
    // Reset genres and artists
    selectedGenres = [];
    selectedArtists = [];
    renderGenreTags();
    renderArtistTags();

    // Reset image previews
    const posterPreview = document.getElementById('poster-preview');
    const posterUploadArea = document.getElementById('poster-upload-area');
    const coverPreview = document.getElementById('cover-preview');
    const coverUploadArea = document.getElementById('cover-upload-area');

    if (posterPreview) posterPreview.classList.add('hidden');
    if (posterUploadArea) posterUploadArea.classList.remove('hidden');
    if (coverPreview) coverPreview.classList.add('hidden');
    if (coverUploadArea) coverUploadArea.classList.remove('hidden');

    // Clear file inputs
    const posterInput = document.querySelector('[name="poster"]');
    const coverInput = document.querySelector('[name="cover_image"]');
    if (posterInput) posterInput.value = '';
    if (coverInput) coverInput.value = '';

    // Reset form
    const form = document.getElementById('create-event-form');
    if (form) form.reset();

    // Reset saved event ID
    const savedIdEl = document.getElementById('saved-event-id');
    if (savedIdEl) savedIdEl.value = '';
}

async function loadEventForEdit(eventId) {
    // Show the form first
    showCreateForm();

    // Fix URL for edit mode (showCreateForm sets it to action=create)
    history.replaceState({}, '', `/organizator/event/${eventId}?action=edit`);

    // Update page title for edit mode
    const titleEl = document.querySelector('#create-event-view h1');
    if (titleEl) titleEl.textContent = 'Editare eveniment';

    // Set the event ID so subsequent saves update instead of create
    document.getElementById('saved-event-id').value = eventId;

    // Show status actions section (only in edit mode)
    const statusActions = document.getElementById('event-status-actions');
    if (statusActions) statusActions.classList.remove('hidden');

    try {
        const response = await AmbiletAPI.organizer.getEvent(eventId);
        const event = response.data?.event || response.event || response.data || response;

        if (!event || !event.id) {
            AmbiletNotifications.error('Evenimentul nu a fost gasit.');
            return;
        }

        // Load current status flags
        currentEventStatus = {
            is_sold_out: event.is_sold_out || false,
            door_sales_only: event.door_sales_only || false,
            is_postponed: event.is_postponed || false,
            is_cancelled: event.is_cancelled || false,
            is_published: event.is_public || event.status === 'published',
            slug: event.slug
        };
        updateStatusIndicators();

        // Handle published event - hide draft buttons, update submit button
        const isPublished = currentEventStatus.is_published;
        const saveDraftBtn = document.getElementById('save-draft-btn');
        const bottomDraftBtns = document.querySelectorAll('button[onclick="saveEventDraft()"]');
        const submitReviewBtn = document.getElementById('submit-review-btn');

        if (isPublished) {
            // Hide "Salveaza ciorna" buttons for published events
            if (saveDraftBtn) saveDraftBtn.style.display = 'none';
            bottomDraftBtns.forEach(btn => {
                if (btn !== saveDraftBtn) btn.style.display = 'none';
            });
            // Change submit button text to "Salvează modificările"
            if (submitReviewBtn) {
                submitReviewBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Salvează modificările';
            }
        } else {
            // Show buttons for draft events
            if (saveDraftBtn) saveDraftBtn.style.display = '';
            bottomDraftBtns.forEach(btn => btn.style.display = '');
            if (submitReviewBtn) {
                submitReviewBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Salveaza si trimite spre aprobare';
            }
        }

        const form = document.getElementById('create-event-form');

        // Step 1: Basic details
        if (event.name) form.querySelector('[name="name"]').value = event.name;
        if (event.short_description) {
            form.querySelector('[name="short_description"]').value = event.short_description;
            // Update word count
            const wordCount = event.short_description.trim().split(/\s+/).filter(w => w.length > 0).length;
            const countEl = document.getElementById('short-desc-count');
            if (countEl) countEl.textContent = wordCount;
        }
        if (event.tags) {
            const tagsStr = Array.isArray(event.tags) ? event.tags.join(', ') : event.tags;
            form.querySelector('[name="tags"]').value = tagsStr;
        }

        // Category
        if (event.marketplace_event_category_id) {
            // Wait for categories to load then set value
            setTimeout(() => {
                const catSelect = form.querySelector('[name="marketplace_event_category_id"]');
                if (catSelect) {
                    catSelect.value = event.marketplace_event_category_id;
                    onCategoryChange(event.marketplace_event_category_id);
                }
            }, 500);
        }

        // Step 2: Schedule - parse starts_at/ends_at/doors_open_at
        if (event.starts_at) {
            const startDt = new Date(event.starts_at);
            const startDate = startDt.toISOString().split('T')[0];
            const startTime = startDt.toTimeString().slice(0, 5);
            form.querySelector('[name="start_date"]').value = startDate;
            form.querySelector('[name="start_time"]').value = startTime;

            // Determine duration mode
            if (event.ends_at) {
                const endDt = new Date(event.ends_at);
                const endDate = endDt.toISOString().split('T')[0];
                const endTime = endDt.toTimeString().slice(0, 5);

                if (endDate === startDate) {
                    // Single day
                    const radio = form.querySelector('[name="duration_mode"][value="single_day"]');
                    if (radio) { radio.checked = true; onDurationModeChange('single_day'); }
                    form.querySelector('[name="end_time_single"]').value = endTime;
                } else {
                    // Date range
                    const radio = form.querySelector('[name="duration_mode"][value="range"]');
                    if (radio) { radio.checked = true; onDurationModeChange('range'); }
                    form.querySelector('[name="end_date"]').value = endDate;
                    const endTimeInput = form.querySelector('[name="end_time"]');
                    if (endTimeInput) endTimeInput.value = endTime;
                }
            } else {
                // No end date - single day mode
                const radio = form.querySelector('[name="duration_mode"][value="single_day"]');
                if (radio) { radio.checked = true; onDurationModeChange('single_day'); }
            }

            // Doors open
            if (event.doors_open_at) {
                const doorDt = new Date(event.doors_open_at);
                const doorTime = doorDt.toTimeString().slice(0, 5);
                const durationMode = form.querySelector('[name="duration_mode"]:checked')?.value;
                if (durationMode === 'range') {
                    form.querySelector('[name="door_time_range"]').value = doorTime;
                } else {
                    form.querySelector('[name="door_time"]').value = doorTime;
                }
            }
        }

        // Step 3: Venue
        if (event.venue_name) form.querySelector('[name="venue_name"]').value = event.venue_name;
        if (event.venue_city) form.querySelector('[name="venue_city"]').value = event.venue_city;
        if (event.venue_address) form.querySelector('[name="venue_address"]').value = event.venue_address;
        if (event.venue_id) document.getElementById('selected-venue-id').value = event.venue_id;

        // Links
        if (event.website_url) form.querySelector('[name="website_url"]').value = event.website_url;
        if (event.facebook_url) form.querySelector('[name="facebook_url"]').value = event.facebook_url;

        // Step 4: Content - set editors content after they initialize
        setTimeout(() => {
            if (event.description && descriptionEditor) {
                descriptionEditor.setContent(event.description);
            }
            if (event.ticket_terms && ticketTermsEditor) {
                ticketTermsEditor.setContent(event.ticket_terms);
            }
            // Update summaries after setting editor content
            updateSummaries();
        }, 1000);

        // Step 6: Ticket types
        if (event.ticket_types && event.ticket_types.length > 0) {
            const container = document.getElementById('ticket-types-container');
            // Clear default ticket type
            container.innerHTML = '';
            ticketTypeCount = 0;

            event.ticket_types.forEach((tt, i) => {
                ticketTypeCount = i + 1;
                const removeBtn = i === 0 ? 'hidden' : '';
                container.innerHTML += `
                    <div class="p-4 border border-gray-200 ticket-type-item rounded-xl" data-index="${i}">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-secondary">Tip bilet #${i + 1}</h4>
                            <button type="button" onclick="removeTicketType(this)" class="${removeBtn} text-red-400 hover:text-red-600 remove-ticket-btn">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label class="text-xs label">Nume bilet <span class="text-red-500">*</span></label>
                                <input type="text" name="ticket_name_${i}" required class="input" placeholder="ex: Standard, VIP" value="${tt.name || ''}">
                            </div>
                            <div>
                                <label class="text-xs label">Pret (RON) <span class="text-red-500">*</span></label>
                                <input type="number" name="ticket_price_${i}" required class="input" placeholder="0.00" step="0.01" min="0" value="${tt.price || 0}">
                            </div>
                            <div>
                                <label class="text-xs label">Stoc bilete</label>
                                <input type="number" name="ticket_quantity_${i}" class="input" placeholder="Nelimitat" min="1" value="${tt.quantity || ''}">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="text-xs label">Descriere bilet</label>
                            <input type="text" name="ticket_desc_${i}" class="input" placeholder="ex: Acces general" value="${tt.description || ''}">
                        </div>
                        <div class="grid gap-3 mt-3 md:grid-cols-2">
                            <div>
                                <label class="text-xs label">Min. bilete/comanda</label>
                                <input type="number" name="ticket_min_${i}" class="input" placeholder="1" min="1" value="${tt.min_per_order || ''}">
                            </div>
                            <div>
                                <label class="text-xs label">Max. bilete/comanda</label>
                                <input type="number" name="ticket_max_${i}" class="input" placeholder="10" min="1" value="${tt.max_per_order || ''}">
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // Step 7: Sales settings
        if (event.capacity) form.querySelector('[name="capacity"]').value = event.capacity;
        if (event.max_tickets_per_order) form.querySelector('[name="max_tickets_per_order"]').value = event.max_tickets_per_order;
        if (event.sales_start_at) {
            const dt = new Date(event.sales_start_at);
            form.querySelector('[name="sales_start_at"]').value = dt.toISOString().slice(0, 16);
        }
        if (event.sales_end_at) {
            const dt = new Date(event.sales_end_at);
            form.querySelector('[name="sales_end_at"]').value = dt.toISOString().slice(0, 16);
        }

        // Genres - populate after category is loaded
        if (event.genres && event.genres.length > 0) {
            setTimeout(() => {
                selectedGenres = event.genres.map(g => ({ id: g.id, name: g.name }));
                renderGenreTags();
                // Show genres container if hidden
                const genresContainer = document.getElementById('genres-container');
                if (genresContainer) genresContainer.classList.remove('hidden');
            }, 600); // Wait for category change to complete
        }

        // Artists - populate selected artists
        if (event.artists && event.artists.length > 0) {
            selectedArtists = event.artists.map(a => ({ id: a.id, name: a.name }));
            renderArtistTags();
        }

        // Poster image - show preview if exists
        if (event.image) {
            const posterPreview = document.getElementById('poster-preview');
            const posterImg = document.getElementById('poster-img');
            const posterUploadArea = document.getElementById('poster-upload-area');
            if (posterPreview && posterImg) {
                posterImg.src = getStorageUrl(event.image);
                posterPreview.classList.remove('hidden');
                if (posterUploadArea) posterUploadArea.classList.add('hidden');
            }
        }

        // Cover image - show preview if exists
        if (event.cover_image) {
            const coverPreview = document.getElementById('cover-preview');
            const coverImg = document.getElementById('cover-img');
            const coverUploadArea = document.getElementById('cover-upload-area');
            if (coverPreview && coverImg) {
                coverImg.src = getStorageUrl(event.cover_image);
                coverPreview.classList.remove('hidden');
                if (coverUploadArea) coverUploadArea.classList.add('hidden');
            }
        }

        // Update summaries
        updateSummaries();

    } catch (error) {
        AmbiletNotifications.error('Eroare la incarcarea evenimentului: ' + (error.message || 'Incearca din nou.'));
    }
}

function hideCreateForm() {
    document.getElementById('create-event-view').classList.add('hidden');
    document.getElementById('events-view').classList.remove('hidden');

    // Reset title back
    const titleEl = document.querySelector('#create-event-view h1');
    if (titleEl) titleEl.textContent = 'Eveniment nou';

    // Reset saved event ID
    document.getElementById('saved-event-id').value = '';

    history.pushState({}, '', '/organizator/events');
    loadEvents();
}

// ==================== CATEGORIES & GENRES ====================

async function loadCategories() {
    try {
        const response = await AmbiletAPI.organizer.getEventCategories();
        const categories = response.data?.categories || response.categories || [];
        categoriesData = categories;
        const select = document.getElementById('category-select');
        // Keep first option
        select.innerHTML = '<option value="">Selecteaza categoria</option>';
        categories.forEach(cat => {
            const icon = cat.icon_emoji ? cat.icon_emoji + ' ' : '';
            select.innerHTML += `<option value="${cat.id}" data-type-ids="${JSON.stringify(cat.event_type_ids || [])}">${icon}${cat.name}</option>`;
        });
    } catch (e) {
        console.error('Failed to load categories:', e);
    }
}

async function onCategoryChange(categoryId) {
    const genresContainer = document.getElementById('genres-container');
    const typeIdsInput = document.getElementById('selected-event-type-ids');

    if (!categoryId) {
        genresContainer.classList.add('hidden');
        availableGenres = [];
        selectedGenres = [];
        renderGenreTags();
        typeIdsInput.value = '';
        return;
    }

    const category = categoriesData.find(c => c.id == categoryId);
    const typeIds = category?.event_type_ids || [];
    typeIdsInput.value = JSON.stringify(typeIds);

    if (typeIds.length === 0) {
        genresContainer.classList.add('hidden');
        availableGenres = [];
        return;
    }

    try {
        const response = await AmbiletAPI.organizer.getEventGenres(typeIds);
        const genres = response.data?.genres || response.genres || [];

        if (genres.length === 0) {
            genresContainer.classList.add('hidden');
            availableGenres = [];
            return;
        }

        availableGenres = genres;
        genresContainer.classList.remove('hidden');
        // Clear selections that are no longer valid
        selectedGenres = selectedGenres.filter(sg => genres.some(g => g.id === sg.id));
        renderGenreTags();
    } catch (e) {
        console.error('Failed to load genres:', e);
        genresContainer.classList.add('hidden');
    }
}

// ==================== GENRE MULTISELECT ====================

function initGenreSearch() {
    const input = document.getElementById('genres-search-input');
    const dropdown = document.getElementById('genres-dropdown');

    input.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        const filtered = availableGenres.filter(g =>
            !selectedGenres.some(sg => sg.id === g.id) &&
            (query === '' || g.name.toLowerCase().includes(query))
        );
        if (filtered.length === 0) {
            dropdown.classList.add('hidden');
            return;
        }
        dropdown.innerHTML = filtered.map(g =>
            `<div class="multiselect-option" onclick="selectGenre(${g.id}, '${g.name.replace(/'/g, "\\'")}')">${g.name}</div>`
        ).join('');
        dropdown.classList.remove('hidden');
    });

    input.addEventListener('focus', function() {
        this.dispatchEvent(new Event('input'));
    });

    input.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });

    // Click on wrapper focuses input
    document.getElementById('genres-multiselect').addEventListener('click', () => input.focus());
}

function selectGenre(id, name) {
    if (!selectedGenres.some(g => g.id === id)) {
        selectedGenres.push({ id, name });
        renderGenreTags();
    }
    document.getElementById('genres-search-input').value = '';
    document.getElementById('genres-dropdown').classList.add('hidden');
}

function removeGenre(id) {
    selectedGenres = selectedGenres.filter(g => g.id !== id);
    renderGenreTags();
}

function renderGenreTags() {
    const container = document.getElementById('genres-selected');
    container.innerHTML = selectedGenres.map(g =>
        `<span class="multiselect-tag">${g.name}<button type="button" onclick="removeGenre(${g.id})">&times;</button></span>`
    ).join('');
}

function getSelectedGenreIds() {
    return selectedGenres.map(g => g.id);
}

// ==================== ARTIST MULTISELECT ====================

function initArtistSearch() {
    const input = document.getElementById('artists-search-input');
    const dropdown = document.getElementById('artists-dropdown');

    input.addEventListener('input', function() {
        clearTimeout(artistSearchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        artistSearchTimeout = setTimeout(async () => {
            try {
                const response = await AmbiletAPI.organizer.searchArtists(query);
                const artists = response.data?.artists || response.artists || [];
                const filtered = artists.filter(a => !selectedArtists.some(sa => sa.id === a.id));

                let html = filtered.map(a =>
                    `<div class="multiselect-option" onclick="selectArtist(${a.id}, '${a.name.replace(/'/g, "\\'")}')">${a.name}</div>`
                ).join('');

                // Always show "create new" option
                html += `<div class="multiselect-option create-new" onclick="createNewArtist()">+ Adauga "${query}" ca artist nou</div>`;

                dropdown.innerHTML = html;
                dropdown.classList.remove('hidden');
            } catch (e) {
                console.error('Artist search failed:', e);
                dropdown.classList.add('hidden');
            }
        }, 300);
    });

    input.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });

    document.getElementById('artists-multiselect').addEventListener('click', () => input.focus());
}

function selectArtist(id, name) {
    if (!selectedArtists.some(a => a.id === id)) {
        selectedArtists.push({ id, name });
        renderArtistTags();
    }
    document.getElementById('artists-search-input').value = '';
    document.getElementById('artists-dropdown').classList.add('hidden');
}

function removeArtist(id) {
    selectedArtists = selectedArtists.filter(a => a.id !== id);
    renderArtistTags();
}

function renderArtistTags() {
    const container = document.getElementById('artists-selected');
    container.innerHTML = selectedArtists.map(a =>
        `<span class="multiselect-tag">${a.name}<button type="button" onclick="removeArtist(${a.id})">&times;</button></span>`
    ).join('');
}

async function createNewArtist() {
    const input = document.getElementById('artists-search-input');
    const name = input.value.trim();
    if (!name) return;

    try {
        const response = await AmbiletAPI.organizer.createArtist(name);
        const artist = response.data?.artist || response.artist;
        if (artist) {
            selectArtist(artist.id, artist.name);
        }
    } catch (e) {
        console.error('Failed to create artist:', e);
        AmbiletNotifications?.error?.('Nu s-a putut crea artistul');
    }
}

// ==================== DURATION MODE ====================

function onDurationModeChange(mode) {
    const scheduleFields = document.getElementById('schedule-fields');
    const endDateFields = document.getElementById('end-date-fields');
    const singleEndTime = document.getElementById('single-end-time');
    const rangeDoorTime = document.getElementById('range-door-time');
    const hint = document.getElementById('duration-mode-hint');

    scheduleFields.classList.remove('hidden');
    hint.classList.add('hidden');

    if (mode === 'single_day') {
        endDateFields.classList.add('hidden');
        singleEndTime.classList.remove('hidden');
        rangeDoorTime.classList.add('hidden');
    } else if (mode === 'range') {
        endDateFields.classList.remove('hidden');
        singleEndTime.classList.add('hidden');
        rangeDoorTime.classList.remove('hidden');
    }

    updateSummaries();
}

// ==================== VENUE SEARCH ====================

function initVenueSearch() {
    const input = document.getElementById('venue-search-input');
    const dropdown = document.getElementById('venue-dropdown');

    async function searchVenues(query) {
        try {
            const response = await AmbiletAPI.organizer.searchVenues(query);
            const venues = response.data?.venues || response.venues || [];

            if (venues.length === 0) {
                dropdown.innerHTML = '<div class="venue-option" style="cursor:default;color:#9ca3af;">Niciun rezultat găsit</div>';
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = venues.map(venue => `
                <div class="venue-option ${venue.is_marketplace ? 'bg-amber-50' : ''}" onclick="selectVenue(${JSON.stringify(venue).replace(/"/g, '&quot;')})">
                    <div class="flex items-center gap-2">
                        <div class="text-sm font-medium text-secondary">${venue.name}</div>
                        ${venue.is_marketplace ? '<span class="text-[10px] px-1.5 py-0.5 bg-amber-200 text-amber-800 rounded font-medium">Partener AmBilet</span>' : ''}
                    </div>
                    <div class="text-xs text-muted">${[venue.city, venue.address].filter(Boolean).join(' - ')}</div>
                </div>
            `).join('');
            dropdown.classList.remove('hidden');
        } catch (e) {
            console.error('Venue search failed:', e);
            dropdown.innerHTML = '<div class="venue-option" style="cursor:default;color:#ef4444;">Eroare la căutare</div>';
            dropdown.classList.remove('hidden');
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(venueSearchTimeout);
        const query = this.value.trim();
        document.getElementById('selected-venue-id').value = '';

        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        venueSearchTimeout = setTimeout(() => searchVenues(query), 300);
    });

    input.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            searchVenues(query);
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });
}

function selectVenue(venue) {
    document.getElementById('venue-search-input').value = venue.name;
    document.getElementById('venue-city-input').value = venue.city || '';
    document.getElementById('venue-address-input').value = venue.address || '';
    document.getElementById('selected-venue-id').value = venue.id;
    document.getElementById('venue-dropdown').classList.add('hidden');
    updateSummaries();
}

// ==================== WYSIWYG EDITORS ====================

function initEditors() {
    if (descriptionEditor) return; // Already initialized

    const baseConfig = {
        base_url: 'https://cdn.jsdelivr.net/npm/tinymce@6.8.5',
        suffix: '.min',
        menubar: false,
        statusbar: false,
        plugins: 'lists link autolink',
        toolbar: 'bold italic underline | bullist numlist | link | hr | undo redo | removeformat',
        content_style: 'body { font-family: Plus Jakarta Sans, sans-serif; font-size: 14px; }',
        paste_as_text: false,
        paste_block_drop: false,
        smart_paste: true,
        branding: false,
        promotion: false,
        license_key: 'gpl'
    };

    tinymce.init({
        ...baseConfig,
        selector: '#description-editor',
        height: 250,
        placeholder: 'Scrie descrierea evenimentului aici...',
        setup: function(editor) {
            editor.on('Change KeyUp', function() { updateSummaries(); });
            editor.on('init', function() { descriptionEditor = editor; });
        }
    });

    tinymce.init({
        ...baseConfig,
        selector: '#ticket-terms-editor',
        height: 200,
        placeholder: 'Conditii de participare, restrictii, politica de retur...',
        setup: function(editor) {
            editor.on('init', function() { ticketTermsEditor = editor; });
        }
    });
}

// ==================== SHORT DESCRIPTION WORD COUNT ====================

function initShortDescWordCount() {
    const input = document.getElementById('short-desc-input');
    const counter = document.getElementById('short-desc-count');

    input.addEventListener('input', function () {
        const words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
        const wordCount = words.length;
        counter.textContent = wordCount;

        if (wordCount > 120) {
            counter.parentElement.classList.add('text-red-500');
            counter.parentElement.classList.remove('text-muted');
        } else {
            counter.parentElement.classList.remove('text-red-500');
            counter.parentElement.classList.add('text-muted');
        }
        updateSummaries();
    });
}

// ==================== ACCORDION ====================

function toggleAccordion(step) {
    const section = document.querySelector(`.accordion-section[data-step="${step}"]`);
    const body = document.getElementById(`accordion-body-${step}`);
    const isOpen = section.getAttribute('data-open') === 'true';

    if (isOpen) {
        body.classList.add('hidden');
        section.setAttribute('data-open', 'false');
    } else {
        body.classList.remove('hidden');
        section.setAttribute('data-open', 'true');
    }

    updateSummaries();
}

function updateSummaries() {
    const form = document.getElementById('create-event-form');

    // Step 1 summary
    const name = form.querySelector('[name="name"]').value;
    const catSelect = form.querySelector('[name="marketplace_event_category_id"]');
    const catText = catSelect.selectedOptions[0]?.text || '';
    if (name || (catText && catText !== 'Selecteaza categoria')) {
        document.getElementById('summary-1').textContent = [name, catText !== 'Selecteaza categoria' ? catText : ''].filter(Boolean).join(' • ');
    }

    // Step 2 summary
    const durationMode = form.querySelector('[name="duration_mode"]:checked')?.value;
    const startDate = form.querySelector('[name="start_date"]').value;
    const startTime = form.querySelector('[name="start_time"]').value;
    if (startDate) {
        const dateStr = new Date(startDate).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        let summary = dateStr + (startTime ? ` la ${startTime}` : '');
        if (durationMode === 'range') {
            const endDate = form.querySelector('[name="end_date"]').value;
            if (endDate) {
                summary += ' - ' + new Date(endDate).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short' });
            }
        }
        document.getElementById('summary-2').textContent = summary;
    }

    // Step 3 summary
    const venueName = form.querySelector('[name="venue_name"]').value;
    const venueCity = form.querySelector('[name="venue_city"]').value;
    if (venueName || venueCity) {
        document.getElementById('summary-3').textContent = [venueName, venueCity].filter(Boolean).join(', ');
    }

    // Step 4 summary
    const desc = descriptionEditor ? descriptionEditor.getContent() : form.querySelector('[name="description"]').value;
    if (desc) {
        const plainText = desc.replace(/<[^>]*>/g, '').trim();
        document.getElementById('summary-4').textContent = plainText.substring(0, 60) + (plainText.length > 60 ? '...' : '');
    }

    // Step 5 summary
    const posterInput = form.querySelector('[name="poster"]');
    const coverInput = form.querySelector('[name="cover_image"]');
    const mediaItems = [];
    if (posterInput && posterInput.files.length > 0) mediaItems.push('Poster');
    if (coverInput && coverInput.files.length > 0) mediaItems.push('Cover');
    document.getElementById('summary-5').textContent = mediaItems.length > 0 ? mediaItems.join(', ') + ' adăugate' : '';

    // Step 6 summary
    const ticketItems = document.querySelectorAll('.ticket-type-item');
    const ticketSummary = [];
    ticketItems.forEach((item, i) => {
        const tName = item.querySelector(`[name="ticket_name_${i}"]`)?.value;
        const tPrice = item.querySelector(`[name="ticket_price_${i}"]`)?.value;
        if (tName && tPrice) ticketSummary.push(`${tName}: ${tPrice} RON`);
    });
    document.getElementById('summary-6').textContent = ticketSummary.join(' | ');

    // Step 7 summary
    const capacity = form.querySelector('[name="capacity"]').value;
    const maxTickets = form.querySelector('[name="max_tickets_per_order"]').value;
    const settingsItems = [];
    if (capacity) settingsItems.push(`Capacitate: ${capacity}`);
    if (maxTickets) settingsItems.push(`Max/comandă: ${maxTickets}`);
    document.getElementById('summary-7').textContent = settingsItems.join(' • ');

    updateStepIndicators();
}

function updateStepIndicators() {
    const form = document.getElementById('create-event-form');

    const step1Done = !!form.querySelector('[name="name"]').value;
    setStepComplete(1, step1Done);

    const step2Done = !!form.querySelector('[name="start_date"]').value && !!form.querySelector('[name="start_time"]').value;
    setStepComplete(2, step2Done);

    const step3Done = !!form.querySelector('[name="venue_name"]').value && !!form.querySelector('[name="venue_city"]').value;
    setStepComplete(3, step3Done);

    const descriptionContent = descriptionEditor ? descriptionEditor.getContent() : form.querySelector('[name="description"]').value;
    const step4Done = !!descriptionContent && descriptionContent.trim().length > 0;
    setStepComplete(4, step4Done);

    const posterInput = form.querySelector('[name="poster"]');
    const coverInput = form.querySelector('[name="cover_image"]');
    const step5Done = (posterInput?.files?.length > 0) || (coverInput?.files?.length > 0);
    setStepComplete(5, step5Done);

    const firstTicketName = form.querySelector('[name="ticket_name_0"]')?.value;
    const firstTicketPrice = form.querySelector('[name="ticket_price_0"]')?.value;
    const step6Done = !!firstTicketName && !!firstTicketPrice;
    setStepComplete(6, step6Done);

    const step7Done = !!form.querySelector('[name="capacity"]').value || !!form.querySelector('[name="max_tickets_per_order"]').value;
    setStepComplete(7, step7Done);
}

function setStepComplete(step, isComplete) {
    const indicator = document.querySelector(`.accordion-section[data-step="${step}"] .step-indicator`);
    if (!indicator) return;
    if (isComplete) {
        indicator.classList.add('completed');
        indicator.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    } else {
        indicator.classList.remove('completed');
        indicator.textContent = step;
    }
}

// ==================== TICKET TYPES ====================

function addTicketType() {
    const container = document.getElementById('ticket-types-container');
    const index = ticketTypeCount;
    ticketTypeCount++;

    const html = `
        <div class="p-4 border border-gray-200 ticket-type-item rounded-xl" data-index="${index}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-secondary">Tip bilet #${index + 1}</h4>
                <button type="button" onclick="removeTicketType(this)" class="text-red-400 hover:text-red-600 remove-ticket-btn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="text-xs label">Nume bilet <span class="text-red-500">*</span></label>
                    <input type="text" name="ticket_name_${index}" required class="input" placeholder="ex: Standard, VIP, Early Bird">
                </div>
                <div>
                    <label class="text-xs label">Preț (RON) <span class="text-red-500">*</span></label>
                    <input type="number" name="ticket_price_${index}" required class="input" placeholder="0.00" step="0.01" min="0">
                </div>
                <div>
                    <label class="text-xs label">Stoc bilete</label>
                    <input type="number" name="ticket_quantity_${index}" class="input" placeholder="Nelimitat" min="1">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-xs label">Descriere bilet</label>
                <input type="text" name="ticket_desc_${index}" class="input" placeholder="ex: Acces general, loc nenumerotat">
            </div>
            <div class="grid gap-3 mt-3 md:grid-cols-2">
                <div>
                    <label class="text-xs label">Min. bilete/comandă</label>
                    <input type="number" name="ticket_min_${index}" class="input" placeholder="1" min="1">
                </div>
                <div>
                    <label class="text-xs label">Max. bilete/comandă</label>
                    <input type="number" name="ticket_max_${index}" class="input" placeholder="10" min="1">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    updateRemoveButtons();
}

function removeTicketType(btn) {
    const item = btn.closest('.ticket-type-item');
    item.remove();
    updateRemoveButtons();
    renumberTicketTypes();
    updateSummaries();
}

function updateRemoveButtons() {
    const items = document.querySelectorAll('.ticket-type-item');
    items.forEach(item => {
        const btn = item.querySelector('.remove-ticket-btn');
        if (items.length > 1) {
            btn.classList.remove('hidden');
        } else {
            btn.classList.add('hidden');
        }
    });
}

function renumberTicketTypes() {
    const items = document.querySelectorAll('.ticket-type-item');
    items.forEach((item, i) => {
        item.setAttribute('data-index', i);
        item.querySelector('h4').textContent = `Tip bilet #${i + 1}`;
        item.querySelector('[name^="ticket_name_"]').name = `ticket_name_${i}`;
        item.querySelector('[name^="ticket_price_"]').name = `ticket_price_${i}`;
        item.querySelector('[name^="ticket_quantity_"]').name = `ticket_quantity_${i}`;
        item.querySelector('[name^="ticket_desc_"]').name = `ticket_desc_${i}`;
        item.querySelector('[name^="ticket_min_"]').name = `ticket_min_${i}`;
        item.querySelector('[name^="ticket_max_"]').name = `ticket_max_${i}`;
    });
    ticketTypeCount = items.length;
}

// ==================== MEDIA PREVIEWS ====================

function previewPoster(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('poster-img').src = e.target.result;
            document.getElementById('poster-preview').classList.remove('hidden');
            document.getElementById('poster-upload-area').classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
    updateSummaries();
}

function removePoster() {
    document.getElementById('poster-preview').classList.add('hidden');
    document.getElementById('poster-upload-area').classList.remove('hidden');
    document.querySelector('[name="poster"]').value = '';
    updateSummaries();
}

function previewCover(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('cover-img').src = e.target.result;
            document.getElementById('cover-preview').classList.remove('hidden');
            document.getElementById('cover-upload-area').classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
    updateSummaries();
}

function removeCover() {
    document.getElementById('cover-preview').classList.add('hidden');
    document.getElementById('cover-upload-area').classList.remove('hidden');
    document.querySelector('[name="cover_image"]').value = '';
    updateSummaries();
}

// ==================== DRAG & DROP ====================

function initDragDrop() {
    document.querySelectorAll('.drop-zone').forEach(zone => {
        ['dragenter', 'dragover'].forEach(evt => {
            zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.add('border-primary', 'bg-primary/10'); });
        });
        ['dragleave', 'drop'].forEach(evt => {
            zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('border-primary', 'bg-primary/10'); });
        });
        zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length === 0) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) { AmbiletNotifications.error('Doar fișiere imagine sunt acceptate'); return; }
            if (file.size > 5 * 1024 * 1024) { AmbiletNotifications.error('Fișierul depășește 5MB'); return; }
            const input = zone.querySelector('input[type="file"]');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            if (zone.dataset.target === 'poster') { previewPoster(input); }
            else if (zone.dataset.target === 'cover') { previewCover(input); }
        });
    });
}

// ==================== SAVE EVENT ====================

function collectFormData() {
    const form = document.getElementById('create-event-form');
    const durationMode = form.querySelector('[name="duration_mode"]:checked')?.value;

    // Build starts_at from date + time
    const startDate = form.querySelector('[name="start_date"]').value;
    const startTime = form.querySelector('[name="start_time"]').value;
    let startsAt = null;
    if (startDate && startTime) {
        startsAt = `${startDate}T${startTime}:00`;
    } else if (startDate) {
        startsAt = `${startDate}T00:00:00`;
    }

    // Build ends_at
    let endsAt = null;
    if (durationMode === 'range') {
        const endDate = form.querySelector('[name="end_date"]').value;
        const endTime = form.querySelector('[name="end_time"]')?.value;
        if (endDate) {
            endsAt = endTime ? `${endDate}T${endTime}:00` : `${endDate}T23:59:00`;
        }
    } else if (durationMode === 'single_day') {
        const endTimeSingle = form.querySelector('[name="end_time_single"]').value;
        if (endTimeSingle && startDate) {
            endsAt = `${startDate}T${endTimeSingle}:00`;
        }
    }

    // Build doors_open_at
    let doorsOpenAt = null;
    if (durationMode === 'single_day') {
        const doorTime = form.querySelector('[name="door_time"]').value;
        if (doorTime && startDate) doorsOpenAt = `${startDate}T${doorTime}:00`;
    } else if (durationMode === 'range') {
        const doorTimeRange = form.querySelector('[name="door_time_range"]').value;
        if (doorTimeRange && startDate) doorsOpenAt = `${startDate}T${doorTimeRange}:00`;
    }

    // Collect ticket types
    const ticketTypes = [];
    const ticketItems = document.querySelectorAll('.ticket-type-item');
    ticketItems.forEach((item, i) => {
        const tName = item.querySelector(`[name="ticket_name_${i}"]`)?.value;
        const tPrice = item.querySelector(`[name="ticket_price_${i}"]`)?.value;
        if (tName && tPrice !== '') {
            const ticket = { name: tName, price: parseFloat(tPrice) };
            const tQty = item.querySelector(`[name="ticket_quantity_${i}"]`)?.value;
            const tDesc = item.querySelector(`[name="ticket_desc_${i}"]`)?.value;
            const tMin = item.querySelector(`[name="ticket_min_${i}"]`)?.value;
            const tMax = item.querySelector(`[name="ticket_max_${i}"]`)?.value;
            if (tQty) ticket.quantity = parseInt(tQty);
            if (tDesc) ticket.description = tDesc;
            if (tMin) ticket.min_per_order = parseInt(tMin);
            if (tMax) ticket.max_per_order = parseInt(tMax);
            ticketTypes.push(ticket);
        }
    });

    // Parse tags
    const tagsStr = form.querySelector('[name="tags"]').value;
    const tags = tagsStr ? tagsStr.split(',').map(t => t.trim()).filter(Boolean) : null;

    // Build data object
    const data = {
        name: form.querySelector('[name="name"]').value,
        starts_at: startsAt,
        venue_name: form.querySelector('[name="venue_name"]').value,
        venue_city: form.querySelector('[name="venue_city"]').value,
    };

    // Category
    const categoryId = form.querySelector('[name="marketplace_event_category_id"]').value;
    if (categoryId) data.marketplace_event_category_id = parseInt(categoryId);

    // Venue ID (if selected from library)
    const venueId = document.getElementById('selected-venue-id').value;
    if (venueId) data.venue_id = parseInt(venueId);

    // Event genres
    const genreIds = getSelectedGenreIds();
    if (genreIds.length > 0) data.genre_ids = genreIds;

    // Event artists
    const artistIds = selectedArtists.map(a => a.id);
    if (artistIds.length > 0) data.artist_ids = artistIds;

    // Short description
    const shortDesc = form.querySelector('[name="short_description"]').value;
    if (shortDesc) data.short_description = shortDesc;

    // Description
    const description = descriptionEditor ? descriptionEditor.getContent() : '';
    if (description && description !== '<p><br></p>' && description.trim() !== '') data.description = description;

    // Ticket terms
    const ticketTerms = ticketTermsEditor ? ticketTermsEditor.getContent() : '';
    if (ticketTerms && ticketTerms !== '<p><br></p>' && ticketTerms.trim() !== '') data.ticket_terms = ticketTerms;

    if (tags && tags.length > 0) data.tags = tags;
    if (endsAt) data.ends_at = endsAt;
    if (doorsOpenAt) data.doors_open_at = doorsOpenAt;

    const venueAddress = form.querySelector('[name="venue_address"]').value;
    if (venueAddress) data.venue_address = venueAddress;

    const websiteUrl = form.querySelector('[name="website_url"]').value;
    if (websiteUrl) data.website_url = websiteUrl;

    const facebookUrl = form.querySelector('[name="facebook_url"]').value;
    if (facebookUrl) data.facebook_url = facebookUrl;

    const capacity = form.querySelector('[name="capacity"]').value;
    if (capacity) data.capacity = parseInt(capacity);

    const maxTickets = form.querySelector('[name="max_tickets_per_order"]').value;
    if (maxTickets) data.max_tickets_per_order = parseInt(maxTickets);

    const salesStart = form.querySelector('[name="sales_start_at"]').value;
    if (salesStart) data.sales_start_at = salesStart;

    const salesEnd = form.querySelector('[name="sales_end_at"]').value;
    if (salesEnd) data.sales_end_at = salesEnd;

    if (ticketTypes.length > 0) data.ticket_types = ticketTypes;

    return data;
}

async function saveEventDraft() {
    const data = collectFormData();

    if (!data.name) {
        AmbiletNotifications.error('Numele evenimentului este obligatoriu.');
        toggleAccordion(1);
        return;
    }

    // Word count validation for short description
    if (data.short_description) {
        const wordCount = data.short_description.trim().split(/\s+/).filter(w => w.length > 0).length;
        if (wordCount > 120) {
            AmbiletNotifications.error('Descrierea scurtă nu poate depăși 120 de cuvinte.');
            toggleAccordion(1);
            return;
        }
    }

    data.is_draft = true;

    const btnText = document.getElementById('save-btn-text');
    const btnSpinner = document.getElementById('save-btn-spinner');
    const saveStatus = document.getElementById('save-status');
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {
        const savedEventId = document.getElementById('saved-event-id').value;
        let result;

        if (savedEventId) {
            result = await AmbiletAPI.organizer.updateEvent(savedEventId, data);
        } else {
            result = await AmbiletAPI.organizer.createEvent(data);
        }

        if (result.success !== false) {
            const eventId = result.data?.event?.id || result.data?.id || savedEventId;
            if (eventId) {
                document.getElementById('saved-event-id').value = eventId;
            }

            saveStatus.textContent = 'Salvat la ' + new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
            saveStatus.classList.remove('hidden');
            AmbiletNotifications.success('Evenimentul a fost salvat ca ciornă!');
        } else {
            const msg = result.message || 'Eroare la salvare.';
            AmbiletNotifications.error(msg);
        }
    } catch (error) {
        const errorMsg = error.message || 'A apărut o eroare. Încearcă din nou.';
        AmbiletNotifications.error(errorMsg);
    }

    btnText.classList.remove('hidden');
    btnSpinner.classList.add('hidden');
}

async function saveAndSubmitEvent() {
    const data = collectFormData();

    if (!data.name) {
        AmbiletNotifications.error('Numele evenimentului este obligatoriu.');
        toggleAccordion(1);
        return;
    }
    if (!data.starts_at) {
        AmbiletNotifications.error('Data și ora evenimentului sunt obligatorii.');
        toggleAccordion(2);
        return;
    }
    if (!data.venue_name || !data.venue_city) {
        AmbiletNotifications.error('Numele locației și orașul sunt obligatorii.');
        toggleAccordion(3);
        return;
    }
    if (!data.ticket_types || data.ticket_types.length === 0) {
        AmbiletNotifications.error('Adaugă cel puțin un tip de bilet pentru a trimite spre aprobare.');
        toggleAccordion(6);
        return;
    }

    try {
        const savedEventId = document.getElementById('saved-event-id').value;
        let eventId = savedEventId;

        if (savedEventId) {
            await AmbiletAPI.organizer.updateEvent(savedEventId, data);
        } else {
            const result = await AmbiletAPI.organizer.createEvent(data);
            if (result.success === false) {
                AmbiletNotifications.error(result.message || 'Eroare la creare.');
                return;
            }
            eventId = result.data?.event?.id || result.data?.id;
            if (eventId) {
                document.getElementById('saved-event-id').value = eventId;
            }
        }

        if (eventId) {
            const submitResult = await AmbiletAPI.organizer.submitEvent(eventId);
            if (submitResult.success !== false) {
                AmbiletNotifications.success('Evenimentul a fost trimis spre aprobare!');
                setTimeout(() => hideCreateForm(), 1500);
            } else {
                AmbiletNotifications.error(submitResult.message || 'Eroare la trimiterea spre aprobare.');
            }
        } else {
            AmbiletNotifications.error('Nu s-a putut identifica evenimentul creat.');
        }
    } catch (error) {
        AmbiletNotifications.error(error.message || 'A apărut o eroare. Încearcă din nou.');
    }
}

// ==================== EVENT LISTENERS ====================

window.addEventListener('popstate', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'create') {
        showCreateForm();
    } else {
        hideCreateForm();
    }
});

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        if (!document.getElementById('create-event-view').classList.contains('hidden')) {
            e.preventDefault();
            saveEventDraft();
        }
    }
});

// Add change listeners for summary updates
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#create-event-form input, #create-event-form select, #create-event-form textarea').forEach(el => {
        el.addEventListener('change', updateSummaries);
        el.addEventListener('input', debounce(updateSummaries, 300));
    });
});

function debounce(fn, ms) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}

// ============ EVENT STATUS ACTIONS ============

// Current event status flags
let currentEventStatus = {
    is_sold_out: false,
    door_sales_only: false,
    is_postponed: false,
    is_cancelled: false
};

function updateStatusIndicators() {
    const container = document.getElementById('status-indicators');
    if (!container) return;

    let html = '';
    if (currentEventStatus.is_sold_out) {
        html += '<span class="badge badge-info">Sold Out</span>';
    }
    if (currentEventStatus.door_sales_only) {
        html += '<span class="badge badge-warning">Door Sales Only</span>';
    }
    if (currentEventStatus.is_postponed) {
        html += '<span class="badge badge-warning">Amânat</span>';
    }
    if (currentEventStatus.is_cancelled) {
        html += '<span class="badge badge-error">Anulat</span>';
    }
    container.innerHTML = html;

    // Update button states
    const btnSoldOut = document.getElementById('btn-sold-out');
    const btnDoorSales = document.getElementById('btn-door-sales');
    const btnPostponed = document.getElementById('btn-postponed');
    const btnCancelled = document.getElementById('btn-cancelled');

    if (btnSoldOut) {
        btnSoldOut.classList.toggle('btn-success', currentEventStatus.is_sold_out);
        btnSoldOut.classList.toggle('btn-secondary', !currentEventStatus.is_sold_out);
        btnSoldOut.querySelector('span').textContent = currentEventStatus.is_sold_out ? 'Anulează Sold Out' : 'Sold Out';
    }
    if (btnDoorSales) {
        btnDoorSales.classList.toggle('btn-success', currentEventStatus.door_sales_only);
        btnDoorSales.classList.toggle('btn-secondary', !currentEventStatus.door_sales_only);
        btnDoorSales.querySelector('span').textContent = currentEventStatus.door_sales_only ? 'Anulează Door Sales' : 'Door Sales Only';
    }
    if (btnPostponed) {
        btnPostponed.classList.toggle('btn-success', currentEventStatus.is_postponed);
        btnPostponed.classList.toggle('btn-warning', !currentEventStatus.is_postponed);
        btnPostponed.querySelector('span').textContent = currentEventStatus.is_postponed ? 'Anulează Amânare' : 'Amânat';
    }
    if (btnCancelled) {
        btnCancelled.classList.toggle('btn-success', currentEventStatus.is_cancelled);
        btnCancelled.classList.toggle('btn-error', !currentEventStatus.is_cancelled);
        btnCancelled.querySelector('span').textContent = currentEventStatus.is_cancelled ? 'Anulează Anulare' : 'Anulat';
    }
}

async function toggleSoldOut() {
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    const newValue = !currentEventStatus.is_sold_out;

    try {
        AmbiletNotifications.info(newValue ? 'Se marchează ca Sold Out...' : 'Se anulează Sold Out...');
        const response = await AmbiletAPI.patch(`/organizer/events/${eventId}/status`, { is_sold_out: newValue });
        if (response.success) {
            currentEventStatus.is_sold_out = newValue;
            updateStatusIndicators();
            AmbiletNotifications.success(newValue ? 'Evenimentul a fost marcat ca Sold Out' : 'Sold Out a fost anulat');
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la actualizare');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la actualizare');
    }
}

async function toggleDoorSales() {
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    const newValue = !currentEventStatus.door_sales_only;

    try {
        AmbiletNotifications.info(newValue ? 'Se activează Door Sales Only...' : 'Se dezactivează Door Sales Only...');
        const response = await AmbiletAPI.patch(`/organizer/events/${eventId}/status`, { door_sales_only: newValue });
        if (response.success) {
            currentEventStatus.door_sales_only = newValue;
            updateStatusIndicators();
            AmbiletNotifications.success(newValue ? 'Door Sales Only a fost activat' : 'Door Sales Only a fost dezactivat');
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la actualizare');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la actualizare');
    }
}

function showPostponedModal() {
    const modal = document.getElementById('postponed-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closePostponedModal() {
    const modal = document.getElementById('postponed-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    document.getElementById('postponed-form').reset();
}

async function savePostponed(e) {
    e.preventDefault();
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    const form = e.target;
    const data = {
        is_postponed: true,
        postponed_date: form.postponed_date.value,
        postponed_start_time: form.postponed_start_time.value || null,
        postponed_door_time: form.postponed_door_time.value || null,
        postponed_end_time: form.postponed_end_time.value || null,
        postponed_reason: form.postponed_reason.value || null
    };

    try {
        AmbiletNotifications.info('Se salvează amânarea...');
        const response = await AmbiletAPI.patch(`/organizer/events/${eventId}/status`, data);
        if (response.success) {
            currentEventStatus.is_postponed = true;
            updateStatusIndicators();
            closePostponedModal();
            AmbiletNotifications.success('Evenimentul a fost marcat ca amânat');
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la salvarea amânării');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la salvarea amânării');
    }
}

function showCancelledModal() {
    const modal = document.getElementById('cancelled-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeCancelledModal() {
    const modal = document.getElementById('cancelled-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    document.getElementById('cancelled-form').reset();
}

async function saveCancelled(e) {
    e.preventDefault();
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    if (!confirm('Ești sigur că vrei să anulezi evenimentul? Această acțiune nu poate fi anulată și toate comenzile vor fi rambursate.')) {
        return;
    }

    const form = e.target;
    const data = {
        reason: form.cancel_reason.value
    };

    try {
        AmbiletNotifications.info('Se anulează evenimentul...');
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/cancel`, data);
        if (response.success) {
            currentEventStatus.is_cancelled = true;
            updateStatusIndicators();
            closeCancelledModal();
            AmbiletNotifications.success('Evenimentul a fost anulat. ' + (response.data?.orders_refunded > 0 ? `${response.data.orders_refunded} comenzi au fost rambursate.` : ''));
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la anularea evenimentului');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la anularea evenimentului');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
