<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Evenimente';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = <<<'HTML'
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.2.27/jodit.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.2.27/jodit.min.js"></script>
HTML;
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
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
                    <div class="flex-1 min-w-[200px]"><input type="text" placeholder="Cauta evenimente..." class="input w-full" id="search-input"></div>
                    <select class="input w-auto" id="status-filter"><option value="">Toate statusurile</option><option value="published">Publicate</option><option value="draft">Ciorne</option><option value="ended">Incheiate</option></select>
                    <select class="input w-auto" id="sort-filter"><option value="date_desc">Cele mai recente</option><option value="date_asc">Cele mai vechi</option><option value="sales_desc">Cele mai vandute</option></select>
                </div>

                <div id="events-list" class="space-y-4"><div class="animate-pulse bg-white rounded-2xl border border-border p-6"><div class="flex gap-6"><div class="w-32 h-24 bg-surface rounded-lg"></div><div class="flex-1 space-y-3"><div class="h-5 bg-surface rounded w-1/3"></div><div class="h-4 bg-surface rounded w-1/4"></div></div></div></div></div>

                <div id="no-events" class="hidden text-center py-16 bg-white rounded-2xl border border-border">
                    <div class="w-24 h-24 bg-muted/10 rounded-full flex items-center justify-center mx-auto mb-6"><svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                    <h2 class="text-xl font-bold text-secondary mb-2">Nu ai evenimente inca</h2>
                    <p class="text-muted mb-6">Creeaza primul tau eveniment si incepe sa vinzi bilete!</p>
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
                        <button onclick="hideCreateForm()" class="p-2 rounded-lg hover:bg-white text-muted hover:text-secondary transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-secondary">Eveniment nou</h1>
                            <p class="text-sm text-muted">Completeaza informatiile pas cu pas</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="save-status" class="text-sm text-muted hidden"></span>
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

                    <!-- ============ STEP 1: Detalii Eveniment ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="1">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(1)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">1</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Detalii eveniment</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-1"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5" id="accordion-body-1">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <div>
                                    <label class="label">Numele evenimentului <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required class="input" placeholder="ex: Concert Rock in Parc">
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label">Categorie</label>
                                        <select name="marketplace_event_category_id" class="input" id="category-select" onchange="onCategoryChange(this.value)">
                                            <option value="">Selecteaza categoria</option>
                                            <!-- Populated dynamically -->
                                        </select>
                                    </div>
                                    <div id="genres-container" class="hidden">
                                        <label class="label">Gen eveniment</label>
                                        <div id="genres-list" class="flex flex-wrap gap-2 p-3 border border-gray-200 rounded-xl min-h-[42px] max-h-[140px] overflow-y-auto">
                                            <!-- Populated dynamically as checkboxes -->
                                        </div>
                                        <p class="text-xs text-muted mt-1">Selecteaza genurile aplicabile</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="label">Descriere scurta</label>
                                    <textarea name="short_description" rows="3" class="input" placeholder="O scurta descriere a evenimentului (max 120 cuvinte)" id="short-desc-input"></textarea>
                                    <p class="text-xs text-muted mt-1"><span id="short-desc-count">0</span>/120 cuvinte</p>
                                </div>
                                <div>
                                    <label class="label">Etichete</label>
                                    <input type="text" name="tags" class="input" placeholder="rock, live, outdoor (separate cu virgula)">
                                    <p class="text-xs text-muted mt-1">Separa etichetele cu virgula</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 2: Program ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="2">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(2)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">2</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Program</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-2"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-2">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <!-- Duration Mode Selector -->
                                <div>
                                    <label class="label">Tipul duratei <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 gap-3" id="duration-mode-selector">
                                        <label class="duration-mode-option flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-primary/50 transition-colors">
                                            <input type="radio" name="duration_mode" value="single_day" class="accent-primary" onchange="onDurationModeChange('single_day')">
                                            <div>
                                                <span class="text-sm font-medium text-secondary">O singura zi</span>
                                                <p class="text-xs text-muted">Evenimentul are loc intr-o singura zi</p>
                                            </div>
                                        </label>
                                        <label class="duration-mode-option flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-primary/50 transition-colors">
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
                                    <div class="grid md:grid-cols-2 gap-4">
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
                                        <div class="grid md:grid-cols-2 gap-4">
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
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="label">Ora sfarsit</label>
                                                <input type="time" name="end_time_single" class="input">
                                            </div>
                                            <div>
                                                <label class="label">Ora deschidere usi</label>
                                                <input type="time" name="door_time" class="input">
                                                <p class="text-xs text-muted mt-1">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="range-door-time" class="hidden">
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="label">Ora deschidere usi</label>
                                                <input type="time" name="door_time_range" class="input">
                                                <p class="text-xs text-muted mt-1">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="duration-mode-hint" class="text-sm text-muted italic">
                                    Selecteaza tipul duratei pentru a configura programul.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 3: Locatie ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="3">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(3)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">3</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Locatie</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-3"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-3">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <div>
                                    <label class="label">Nume locatie / sala <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="text" name="venue_name" required class="input" placeholder="Cauta sau scrie numele locatiei..." id="venue-search-input" autocomplete="off">
                                        <div id="venue-dropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                            <!-- Populated dynamically -->
                                        </div>
                                    </div>
                                    <p class="text-xs text-muted mt-1">Cauta in biblioteca de locatii sau scrie manual</p>
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label">Oras <span class="text-red-500">*</span></label>
                                        <input type="text" name="venue_city" required class="input" placeholder="ex: Bucuresti" id="venue-city-input">
                                    </div>
                                    <div>
                                        <label class="label">Adresa</label>
                                        <input type="text" name="venue_address" class="input" placeholder="ex: Str. Lipscani nr. 10" id="venue-address-input">
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
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
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="4">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(4)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">4</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Continut & Descriere</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-4"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-4">
                            <div class="space-y-6 pt-2 border-t border-gray-100">
                                <div>
                                    <label class="label">Descriere completa</label>
                                    <textarea name="description" id="description-editor"></textarea>
                                    <p class="text-xs text-muted mt-1">Descrie evenimentul in detaliu: lineup, program, reguli de acces, etc.</p>
                                </div>
                                <div>
                                    <label class="label">Conditii eveniment</label>
                                    <textarea name="ticket_terms" id="ticket-terms-editor"></textarea>
                                    <p class="text-xs text-muted mt-1">Conditii de participare, restrictii de varsta, reguli speciale, politica de retur, etc.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 5: Media ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="5">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(5)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">5</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Media</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-5"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-5">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="label">Poster (vertical)</label>
                                        <div class="relative">
                                            <div id="poster-preview" class="hidden mb-3">
                                                <img id="poster-img" src="" alt="Poster" class="w-full max-w-[200px] rounded-xl border border-border">
                                                <button type="button" onclick="removePoster()" class="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors" id="poster-upload-area">
                                                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-muted">Click pentru upload</span>
                                                <span class="text-xs text-muted mt-1">JPG, PNG (max 5MB)</span>
                                                <input type="file" name="poster" accept="image/*" class="hidden" onchange="previewPoster(this)">
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label">Imagine cover (orizontala)</label>
                                        <div class="relative">
                                            <div id="cover-preview" class="hidden mb-3">
                                                <img id="cover-img" src="" alt="Cover" class="w-full rounded-xl border border-border">
                                                <button type="button" onclick="removeCover()" class="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors" id="cover-upload-area">
                                                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-muted">Click pentru upload</span>
                                                <span class="text-xs text-muted mt-1">JPG, PNG (recomandat 1200x630)</span>
                                                <input type="file" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 6: Bilete ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="6">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(6)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">6</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Bilete</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-6"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-6">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <p class="text-sm text-muted">Adauga cel putin un tip de bilet. Poti adauga mai multe categorii (ex: Early Bird, Standard, VIP).</p>

                                <div id="ticket-types-container" class="space-y-4">
                                    <!-- First ticket type (default) -->
                                    <div class="ticket-type-item border border-gray-200 rounded-xl p-4" data-index="0">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-sm font-semibold text-secondary">Tip bilet #1</h4>
                                            <button type="button" onclick="removeTicketType(this)" class="text-red-400 hover:text-red-600 hidden remove-ticket-btn">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid md:grid-cols-3 gap-3">
                                            <div>
                                                <label class="label text-xs">Nume bilet <span class="text-red-500">*</span></label>
                                                <input type="text" name="ticket_name_0" required class="input" placeholder="ex: Standard, VIP, Early Bird">
                                            </div>
                                            <div>
                                                <label class="label text-xs">Pret (RON) <span class="text-red-500">*</span></label>
                                                <input type="number" name="ticket_price_0" required class="input" placeholder="0.00" step="0.01" min="0">
                                            </div>
                                            <div>
                                                <label class="label text-xs">Stoc bilete</label>
                                                <input type="number" name="ticket_quantity_0" class="input" placeholder="Nelimitat" min="1">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="label text-xs">Descriere bilet</label>
                                            <input type="text" name="ticket_desc_0" class="input" placeholder="ex: Acces general, loc nenumerotat">
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-3 mt-3">
                                            <div>
                                                <label class="label text-xs">Min. bilete/comanda</label>
                                                <input type="number" name="ticket_min_0" class="input" placeholder="1" min="1">
                                            </div>
                                            <div>
                                                <label class="label text-xs">Max. bilete/comanda</label>
                                                <input type="number" name="ticket_max_0" class="input" placeholder="10" min="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" onclick="addTicketType()" class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-sm font-medium text-muted hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Adauga alt tip de bilet
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 7: Setari vanzari ============ -->
                    <div class="accordion-section bg-white rounded-2xl border border-border overflow-hidden" data-step="7">
                        <button type="button" class="accordion-header w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors" onclick="toggleAccordion(7)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">7</div>
                                <div>
                                    <h3 class="font-semibold text-secondary">Setari vanzari</h3>
                                    <p class="text-xs text-muted mt-0.5 accordion-summary" id="summary-7"></p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-muted accordion-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5 hidden" id="accordion-body-7">
                            <div class="space-y-4 pt-2 border-t border-gray-100">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label">Capacitate totala</label>
                                        <input type="number" name="capacity" class="input" placeholder="ex: 500" min="1">
                                        <p class="text-xs text-muted mt-1">Numarul total de locuri disponibile</p>
                                    </div>
                                    <div>
                                        <label class="label">Max. bilete per comanda</label>
                                        <input type="number" name="max_tickets_per_order" class="input" placeholder="10" min="1" max="50">
                                        <p class="text-xs text-muted mt-1">Cate bilete poate cumpara un client intr-o comanda</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label">Inceput vanzari</label>
                                        <input type="datetime-local" name="sales_start_at" class="input">
                                        <p class="text-xs text-muted mt-1">Cand incep vanzarile (gol = imediat)</p>
                                    </div>
                                    <div>
                                        <label class="label">Sfarsit vanzari</label>
                                        <input type="datetime-local" name="sales_end_at" class="input">
                                        <p class="text-xs text-muted mt-1">Cand se opresc vanzarile (gol = la inceput eveniment)</p>
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

    <style>
        .accordion-body.hidden { display: none; }
        .accordion-section[data-open="true"] .accordion-chevron { transform: rotate(180deg); }
        .accordion-section[data-open="true"] .step-indicator { background-color: var(--color-primary, #6366f1); color: white; }
        .step-indicator.completed { background-color: #10b981 !important; color: white !important; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .duration-mode-option:has(input:checked) { border-color: var(--color-primary, #6366f1); background-color: rgba(99, 102, 241, 0.05); }
        .genre-chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; cursor: pointer; border: 1px solid #e5e7eb; background: white; transition: all 0.15s; }
        .genre-chip:hover { border-color: var(--color-primary, #6366f1); }
        .genre-chip.selected { background-color: var(--color-primary, #6366f1); color: white; border-color: var(--color-primary, #6366f1); }
        .venue-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: background 0.1s; }
        .venue-option:hover { background-color: #f9fafb; }
        .venue-option:last-child { border-bottom: none; }
        .jodit-container { border-radius: 0.75rem !important; border-color: #e5e7eb !important; }
        .jodit-workplace { min-height: 160px; font-size: 0.875rem; }
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

// Check if we should show create form on load
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('action') === 'create') {
    showCreateForm();
} else {
    loadEvents();
}

// ==================== EVENTS LIST ====================

async function loadEvents() {
    try {
        const response = await AmbiletAPI.organizer.getEvents();
        const events = response.data || response || [];
        if (events.length === 0) { document.getElementById('events-list').classList.add('hidden'); document.getElementById('no-events').classList.remove('hidden'); }
        else { renderEvents(events); }
    } catch (error) { document.getElementById('events-list').classList.add('hidden'); document.getElementById('no-events').classList.remove('hidden'); }
}

function renderEvents(events) {
    const container = document.getElementById('events-list');
    const statusColors = { published: 'success', draft: 'warning', ended: 'muted', pending_review: 'info' };
    const statusLabels = { published: 'Publicat', draft: 'Ciorna', ended: 'Incheiat', pending_review: 'In asteptare' };
    container.innerHTML = events.map(event => `
        <div class="bg-white rounded-2xl border border-border p-6 hover:border-primary/30 transition-colors">
            <div class="flex flex-col md:flex-row gap-6">
                <img src="${event.image || AMBILET_CONFIG.PLACEHOLDER_EVENT}" alt="${event.name || event.title}" class="w-full md:w-40 h-28 rounded-xl object-cover">
                <div class="flex-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-secondary mb-1">${event.name || event.title}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted">
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${event.starts_at ? AmbiletUtils.formatDate(event.starts_at) : (event.start_date ? AmbiletUtils.formatDate(event.start_date) : '')}</span>
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>${event.venue_city || event.venue?.city || event.city || ''}</span>
                            </div>
                        </div>
                        <span class="badge badge-${statusColors[event.status] || 'secondary'}">${statusLabels[event.status] || event.status}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mt-4 pt-4 border-t border-border">
                        <div><p class="text-2xl font-bold text-secondary">${event.tickets_sold || 0}</p><p class="text-xs text-muted">Bilete vandute</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${AmbiletUtils.formatCurrency(event.revenue || 0)}</p><p class="text-xs text-muted">Vanzari</p></div>
                        <div class="flex items-center justify-end gap-2">
                            <a href="/organizer/events.php?id=${event.id}" class="btn btn-sm btn-secondary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Editeaza</a>
                            <a href="/event.php?slug=${event.slug}" target="_blank" class="btn btn-sm btn-secondary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// ==================== CREATE FORM ====================

function showCreateForm() {
    document.getElementById('events-view').classList.add('hidden');
    document.getElementById('create-event-view').classList.remove('hidden');
    history.pushState({}, '', '/organizer/events?action=create');
    toggleAccordion(1);
    loadCategories();
    initEditors();
    initVenueSearch();
    initShortDescWordCount();
}

function hideCreateForm() {
    document.getElementById('create-event-view').classList.add('hidden');
    document.getElementById('events-view').classList.remove('hidden');
    history.pushState({}, '', '/organizer/events');
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
    const genresList = document.getElementById('genres-list');
    const typeIdsInput = document.getElementById('selected-event-type-ids');

    if (!categoryId) {
        genresContainer.classList.add('hidden');
        genresList.innerHTML = '';
        typeIdsInput.value = '';
        return;
    }

    // Get event_type_ids from selected category
    const category = categoriesData.find(c => c.id == categoryId);
    const typeIds = category?.event_type_ids || [];
    typeIdsInput.value = JSON.stringify(typeIds);

    if (typeIds.length === 0) {
        genresContainer.classList.add('hidden');
        genresList.innerHTML = '';
        return;
    }

    // Load genres filtered by type_ids
    try {
        const response = await AmbiletAPI.organizer.getEventGenres(typeIds);
        const genres = response.data?.genres || response.genres || [];

        if (genres.length === 0) {
            genresContainer.classList.add('hidden');
            genresList.innerHTML = '';
            return;
        }

        genresContainer.classList.remove('hidden');
        genresList.innerHTML = genres.map(genre => `
            <span class="genre-chip" data-genre-id="${genre.id}" onclick="toggleGenre(this)">
                ${genre.name}
            </span>
        `).join('');
    } catch (e) {
        console.error('Failed to load genres:', e);
        genresContainer.classList.add('hidden');
    }
}

function toggleGenre(chip) {
    chip.classList.toggle('selected');
}

function getSelectedGenreIds() {
    return Array.from(document.querySelectorAll('.genre-chip.selected'))
        .map(chip => parseInt(chip.dataset.genreId));
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

    input.addEventListener('input', function () {
        clearTimeout(venueSearchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        venueSearchTimeout = setTimeout(async () => {
            try {
                const response = await AmbiletAPI.organizer.searchVenues(query);
                const venues = response.data?.venues || response.venues || [];

                if (venues.length === 0) {
                    dropdown.classList.add('hidden');
                    return;
                }

                dropdown.innerHTML = venues.map(venue => `
                    <div class="venue-option" onclick="selectVenue(${JSON.stringify(venue).replace(/"/g, '&quot;')})">
                        <div class="font-medium text-sm text-secondary">${venue.name}</div>
                        <div class="text-xs text-muted">${[venue.city, venue.address].filter(Boolean).join(' - ')}</div>
                    </div>
                `).join('');
                dropdown.classList.remove('hidden');
            } catch (e) {
                console.error('Venue search failed:', e);
                dropdown.classList.add('hidden');
            }
        }, 300);
    });

    // Hide dropdown on blur (with slight delay for click)
    input.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });

    // Clear venue_id if user types manually
    input.addEventListener('input', function () {
        document.getElementById('selected-venue-id').value = '';
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

    const editorConfig = {
        height: 250,
        toolbarButtonSize: 'small',
        buttons: 'bold,italic,underline,|,ul,ol,|,paragraph,|,link,|,hr,|,undo,redo,|,eraser',
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_clear_html',
        language: 'ro',
        uploader: { insertImageAsBase64URI: true }
    };

    descriptionEditor = Jodit.make('#description-editor', {
        ...editorConfig,
        placeholder: 'Scrie descrierea evenimentului aici...',
        events: {
            change: function(value) { updateSummaries(); }
        }
    });

    ticketTermsEditor = Jodit.make('#ticket-terms-editor', {
        ...editorConfig,
        height: 200,
        placeholder: 'Conditii de participare, restrictii, politica de retur...'
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
    const desc = form.querySelector('[name="description"]').value;
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
    document.getElementById('summary-5').textContent = mediaItems.length > 0 ? mediaItems.join(', ') + ' adaugate' : '';

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
    if (maxTickets) settingsItems.push(`Max/comanda: ${maxTickets}`);
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

    const step4Done = !!form.querySelector('[name="description"]').value;
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
        <div class="ticket-type-item border border-gray-200 rounded-xl p-4" data-index="${index}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-secondary">Tip bilet #${index + 1}</h4>
                <button type="button" onclick="removeTicketType(this)" class="text-red-400 hover:text-red-600 remove-ticket-btn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="label text-xs">Nume bilet <span class="text-red-500">*</span></label>
                    <input type="text" name="ticket_name_${index}" required class="input" placeholder="ex: Standard, VIP, Early Bird">
                </div>
                <div>
                    <label class="label text-xs">Pret (RON) <span class="text-red-500">*</span></label>
                    <input type="number" name="ticket_price_${index}" required class="input" placeholder="0.00" step="0.01" min="0">
                </div>
                <div>
                    <label class="label text-xs">Stoc bilete</label>
                    <input type="number" name="ticket_quantity_${index}" class="input" placeholder="Nelimitat" min="1">
                </div>
            </div>
            <div class="mt-3">
                <label class="label text-xs">Descriere bilet</label>
                <input type="text" name="ticket_desc_${index}" class="input" placeholder="ex: Acces general, loc nenumerotat">
            </div>
            <div class="grid md:grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="label text-xs">Min. bilete/comanda</label>
                    <input type="number" name="ticket_min_${index}" class="input" placeholder="1" min="1">
                </div>
                <div>
                    <label class="label text-xs">Max. bilete/comanda</label>
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

    // Short description
    const shortDesc = form.querySelector('[name="short_description"]').value;
    if (shortDesc) data.short_description = shortDesc;

    // Description
    const description = descriptionEditor ? descriptionEditor.value : '';
    if (description && description !== '<p><br></p>' && description.trim() !== '') data.description = description;

    // Ticket terms
    const ticketTerms = ticketTermsEditor ? ticketTermsEditor.value : '';
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

    if (!data.starts_at) {
        AmbiletNotifications.error('Data si ora evenimentului sunt obligatorii.');
        toggleAccordion(2);
        return;
    }

    if (!data.venue_name || !data.venue_city) {
        AmbiletNotifications.error('Numele locatiei si orasul sunt obligatorii.');
        toggleAccordion(3);
        return;
    }

    if (!data.ticket_types || data.ticket_types.length === 0) {
        AmbiletNotifications.error('Adauga cel putin un tip de bilet.');
        toggleAccordion(6);
        return;
    }

    // Word count validation for short description
    if (data.short_description) {
        const wordCount = data.short_description.trim().split(/\s+/).filter(w => w.length > 0).length;
        if (wordCount > 120) {
            AmbiletNotifications.error('Descrierea scurta nu poate depasi 120 de cuvinte.');
            toggleAccordion(1);
            return;
        }
    }

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
            AmbiletNotifications.success('Evenimentul a fost salvat ca ciorna!');
        } else {
            const msg = result.message || 'Eroare la salvare.';
            AmbiletNotifications.error(msg);
        }
    } catch (error) {
        const errorMsg = error.message || 'A aparut o eroare. Incearca din nou.';
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
        AmbiletNotifications.error('Data si ora evenimentului sunt obligatorii.');
        toggleAccordion(2);
        return;
    }
    if (!data.venue_name || !data.venue_city) {
        AmbiletNotifications.error('Numele locatiei si orasul sunt obligatorii.');
        toggleAccordion(3);
        return;
    }
    if (!data.ticket_types || data.ticket_types.length === 0) {
        AmbiletNotifications.error('Adauga cel putin un tip de bilet pentru a trimite spre aprobare.');
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
        AmbiletNotifications.error(error.message || 'A aparut o eroare. Incearca din nou.');
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
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
