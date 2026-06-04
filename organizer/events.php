<?php
/**
 * bilete.online — Organizator › Activități (v3).
 * Routes: /organizator/events  and  /organizator/event/{id}
 *
 * Activity catalog + create/edit form (7-step accordion). Faithful 1:1 port of
 * the ambilet organizer/events.php — same field names, element IDs and JS
 * behavior, restyled to bilete.online v3 and adapted to activities
 * ("eveniment" → "activitate"). Wired to BileteOnlineAPI.organizer.* and the
 * same proxy endpoints (events / event / submit / cancel / status / images /
 * categories / genres / venues / artists).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Activități';
$currentPage = 'events';
$extraHead   = '<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 px-4 lg:px-8">
        <!-- ============================================================ -->
        <!-- ACTIVITIES LIST VIEW -->
        <!-- ============================================================ -->
        <div id="events-view" class="py-4 lg:py-8">
            <!-- Page Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="font-display text-3xl font-bold leading-none">Activitățile tale</h1>
                    <p class="mt-1.5 text-sm text-ink-soft">Gestionează și monitorizează activitățile tale</p>
                </div>
                <button onclick="showCreateForm()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d max-md:hidden">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Activitate nouă
                </button>
            </div>

            <div class="mb-6 flex flex-wrap items-center gap-4">
                <div class="min-w-[200px] flex-1">
                    <input type="text" placeholder="Caută activități…" id="search-input"
                           class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
                <div class="flex flex-wrap gap-2" id="status-pills">
                    <button type="button" class="status-pill active" data-status="ongoing">În derulare</button>
                    <button type="button" class="status-pill" data-status="draft">Ciorne</button>
                    <button type="button" class="status-pill" data-status="ended">Încheiate</button>
                    <button type="button" class="status-pill max-md:hidden" data-status="">Toate</button>
                </div>
            </div>

            <div id="events-list" class="space-y-4">
                <!-- Skeleton: detailed activity cards -->
                <div class="rounded-2xl border-2 border-ink bg-paper p-4 lg:p-6">
                    <div class="flex gap-4 lg:gap-6">
                        <div class="edit-skeleton h-28 w-28 flex-shrink-0 rounded-xl lg:h-28 lg:w-32"></div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="edit-skeleton h-5 w-1/2"></div>
                                <div class="edit-skeleton h-5 w-16 rounded-full"></div>
                            </div>
                            <div class="edit-skeleton h-3.5 w-1/3"></div>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <div class="edit-skeleton h-6 w-16 rounded-full"></div>
                                <div class="edit-skeleton h-6 w-20 rounded-full"></div>
                                <div class="edit-skeleton h-6 w-14 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-4 opacity-80 lg:p-6">
                    <div class="flex gap-4 lg:gap-6">
                        <div class="edit-skeleton h-28 w-28 flex-shrink-0 rounded-xl lg:h-28 lg:w-32"></div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="edit-skeleton h-5 w-2/5"></div>
                            <div class="edit-skeleton h-3.5 w-1/4"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="no-events" class="hidden rounded-2xl border-2 border-ink bg-paper py-16 text-center">
                <div class="mx-auto mb-6 grid h-24 w-24 place-items-center rounded-full bg-ink/5">
                    <svg class="h-12 w-12 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="mb-2 font-display text-xl font-bold">Nu ai activități încă</h2>
                <p class="mb-6 text-ink-soft">Creează prima ta activitate și începe să vinzi bilete!</p>
                <button onclick="showCreateForm()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Creează activitate
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- CREATE / EDIT ACTIVITY ACCORDION FORM -->
        <!-- ============================================================ -->
        <div id="create-event-view" class="hidden">
            <!-- ============ STICKY ACTIVITY HEADER ============ -->
            <div id="event-edit-header" class="sticky top-16 z-30 -mx-4 border-b-2 border-ink/15 bg-paper/85 px-4 py-4 backdrop-blur-md lg:-mx-8 lg:px-8">
                <div class="flex items-start gap-3 lg:gap-4">
                    <button onclick="hideCreateForm()" class="-ml-1 flex-shrink-0 rounded-lg p-2 text-ink-soft transition-colors hover:bg-paper-2 hover:text-ink" title="Înapoi la activități">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate font-display text-xl font-bold lg:text-2xl">Activitate nouă</h1>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-soft">
                            <span id="header-event-date" class="hidden items-center gap-1.5 inline-flex">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span></span>
                            </span>
                            <span id="header-event-venue" class="hidden items-center gap-1.5 inline-flex">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span></span>
                            </span>
                            <span id="header-event-status" class="hidden items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-bold inline-flex">
                                <span class="status-dot h-1.5 w-1.5 rounded-full"></span>
                                <span class="status-label"></span>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-2">
                        <span id="save-status" class="hidden whitespace-nowrap rounded-md border-2 border-ink/15 bg-paper-2 px-2.5 py-1 text-xs text-ink-soft"></span>
                        <button id="header-preview-btn" type="button" class="hidden items-center gap-1.5 rounded-full border-2 border-ink px-3 py-2 text-xs font-bold transition hover:bg-ink hover:text-paper max-md:!hidden inline-flex" onclick="openLivePreview()" title="Live preview">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span class="hidden lg:inline">Preview</span>
                        </button>
                        <button onclick="saveEventDraft()" id="save-draft-btn" class="inline-flex items-center gap-1.5 rounded-full bg-vermilion px-4 py-2 text-xs font-bold text-paper transition hover:bg-vermilion-d">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span id="save-btn-text" class="max-md:hidden">Salvează ciornă</span>
                            <div id="save-btn-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-paper/40 border-t-paper"></div>
                        </button>
                        <button type="button" onclick="saveAndSubmitEvent()" id="header-submit-btn" class="hidden items-center gap-1.5 rounded-full bg-forest px-4 py-2 text-xs font-bold text-paper transition hover:bg-forest/90 inline-flex">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="max-md:hidden">Trimite spre aprobare</span>
                        </button>
                        <button type="button" id="edit-delete-btn" class="hidden items-center gap-1.5 rounded-full bg-vermilion px-4 py-2 text-xs font-bold text-paper transition hover:bg-vermilion-d inline-flex" title="Șterge activitatea">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <span class="max-md:hidden">Șterge</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ TWO-COLUMN LAYOUT: outline sidebar + form ============ -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[260px_1fr]">
                <!-- OUTLINE SIDEBAR (desktop) — scroll-spy navigation -->
                <aside id="edit-outline" class="hidden lg:block">
                    <div class="sticky top-[200px] space-y-0.5 rounded-2xl border-2 border-ink bg-paper p-3">
                        <p class="px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider text-ink-soft">Cuprins</p>
                        <a href="#step-1" data-outline="1" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span class="flex-1">Detalii activitate</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-2" data-outline="2" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="flex-1">Program</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-3" data-outline="3" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="flex-1">Locație</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-4" data-outline="4" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="flex-1">Conținut</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-5" data-outline="5" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="flex-1">Media</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-6" data-outline="6" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            <span class="flex-1">Bilete</span>
                            <span class="outline-status"></span>
                        </a>
                        <a href="#step-7" data-outline="7" class="outline-item">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="flex-1">Setări vânzări</span>
                            <span class="outline-status"></span>
                        </a>
                        <div id="outline-issues" class="mt-2 hidden items-start gap-1.5 rounded-lg border-t-2 border-ink/10 bg-ochre/10 px-2 py-1 pt-2 text-xs text-ochre flex">
                            <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span><span id="outline-issues-count">0</span> probleme rămase</span>
                        </div>
                    </div>
                </aside>

                <!-- Accordion Form -->
                <form id="create-event-form" autocomplete="off" name="event-create-no-autofill" class="min-w-0 space-y-3 pb-16 lg:space-y-4">
                    <!-- Hidden fields -->
                    <input type="hidden" id="saved-event-id" value="">
                    <input type="hidden" id="selected-event-type-ids" value="">
                    <input type="hidden" id="selected-venue-id" value="">

                    <!-- ============ REJECTION BANNER (rejected only) ============ -->
                    <div id="rejection-banner" class="hidden rounded-2xl border-2 border-vermilion/30 bg-vermilion/10 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-vermilion" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-vermilion">Activitatea a fost respinsă</h4>
                                <p class="mt-1 text-sm text-vermilion">Motiv: <span id="rejection-reason-text"></span></p>
                                <p class="mt-2 text-xs text-vermilion/80">Editează activitatea după indicații și apasă „Salvează și trimite spre aprobare" pentru o nouă revizuire.</p>
                            </div>
                        </div>
                    </div>

                    <div id="edit-mode-header" class="hidden"></div>

                    <!-- ============ ACTIVITY STATUS ACTIONS (published only) ============ -->
                    <div id="event-status-actions" class="hidden rounded-2xl border-2 border-ink bg-paper p-5">
                        <h3 class="mb-4 font-display font-bold">Acțiuni activitate</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" onclick="toggleSoldOut()" id="btn-sold-out" class="inline-flex items-center gap-1.5 rounded-full border-2 border-ink px-4 py-2 text-xs font-bold transition hover:bg-ink hover:text-paper">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Sold Out</span>
                            </button>
                            <button type="button" onclick="toggleDoorSales()" id="btn-door-sales" class="inline-flex items-center gap-1.5 rounded-full border-2 border-ink px-4 py-2 text-xs font-bold transition hover:bg-ink hover:text-paper">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <span>Door Sales Only</span>
                            </button>
                            <button type="button" onclick="showPostponedModal()" id="btn-postponed" class="inline-flex items-center gap-1.5 rounded-full bg-ochre px-4 py-2 text-xs font-bold text-paper transition hover:bg-ochre/90">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Amânat</span>
                            </button>
                            <button type="button" onclick="showCancelledModal()" id="btn-cancelled" class="inline-flex items-center gap-1.5 rounded-full bg-vermilion px-4 py-2 text-xs font-bold text-paper transition hover:bg-vermilion-d">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Anulat</span>
                            </button>
                        </div>
                        <div id="status-indicators" class="mt-3 flex flex-wrap gap-2"></div>
                    </div>

                    <!-- ============ STEP 1: Detalii activitate ============ -->
                    <div id="step-1" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="1">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(1)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-vermilion text-sm font-bold text-paper">1</div>
                                <div>
                                    <h3 class="font-display font-bold">Detalii activitate</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-1"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body px-5 pb-5" id="accordion-body-1">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Numele activității <span class="text-vermilion">*</span></label>
                                    <input type="text" name="name" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Tur cu barca pe lac">
                                </div>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Categorie</label>
                                        <select name="marketplace_event_category_id" id="category-select" onchange="onCategoryChange(this.value)" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                            <option value="">Selecteaza categoria</option>
                                        </select>
                                    </div>
                                    <div id="genres-container" class="hidden">
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Gen activitate</label>
                                        <div class="multiselect-wrapper" id="genres-multiselect">
                                            <div class="multiselect-tags" id="genres-selected"></div>
                                            <input type="text" class="multiselect-input" placeholder="Cauta genuri..." id="genres-search-input" autocomplete="off">
                                            <div class="multiselect-dropdown hidden" id="genres-dropdown"></div>
                                        </div>
                                        <p class="mt-1 text-xs text-ink-soft">Selecteaza genurile aplicabile</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Artisti</label>
                                        <div class="multiselect-wrapper" id="artists-multiselect">
                                            <div class="multiselect-tags" id="artists-selected"></div>
                                            <input type="text" class="multiselect-input" placeholder="Cauta artisti..." id="artists-search-input" autocomplete="off">
                                            <div class="multiselect-dropdown hidden" id="artists-dropdown"></div>
                                        </div>
                                        <p class="mt-1 text-xs text-ink-soft">Cauta in biblioteca sau scrie un nume nou</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Descriere scurta</label>
                                    <textarea name="short_description" rows="3" id="short-desc-input" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="O scurta descriere a activității (max 120 cuvinte)"></textarea>
                                    <p class="mt-1 text-xs text-ink-soft"><span id="short-desc-count">0</span>/120 cuvinte</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Etichete</label>
                                    <input type="text" name="tags" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="aventura, outdoor, familie (separate cu virgula)">
                                    <p class="mt-1 text-xs text-ink-soft">Separa etichetele cu virgula</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 2: Program ============ -->
                    <div id="step-2" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="2">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(2)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">2</div>
                                <div>
                                    <h3 class="font-display font-bold">Program</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-2"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-2">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Tipul duratei <span class="text-vermilion">*</span></label>
                                    <div class="grid grid-cols-2 gap-3" id="duration-mode-selector">
                                        <label class="duration-mode-option flex cursor-pointer items-center gap-3 rounded-xl border-2 border-ink/15 p-3 transition-colors hover:border-vermilion/50">
                                            <input type="radio" name="duration_mode" value="single_day" class="accent-vermilion" onchange="onDurationModeChange('single_day')">
                                            <div>
                                                <span class="text-sm font-bold">O singura zi</span>
                                                <p class="text-xs text-ink-soft">Activitatea are loc intr-o singura zi</p>
                                            </div>
                                        </label>
                                        <label class="duration-mode-option flex cursor-pointer items-center gap-3 rounded-xl border-2 border-ink/15 p-3 transition-colors hover:border-vermilion/50">
                                            <input type="radio" name="duration_mode" value="range" class="accent-vermilion" onchange="onDurationModeChange('range')">
                                            <div>
                                                <span class="text-sm font-bold">Interval de zile</span>
                                                <p class="text-xs text-ink-soft">Activitatea se intinde pe mai multe zile</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div id="schedule-fields" class="hidden space-y-4">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Data activitate <span class="text-vermilion">*</span></label>
                                            <input type="date" name="start_date" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora incepere <span class="text-vermilion">*</span></label>
                                            <input type="time" name="start_time" required onclick="this.showPicker?.()" onfocus="this.showPicker?.()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                        </div>
                                    </div>
                                    <div id="end-date-fields" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Data sfarsit <span class="text-vermilion">*</span></label>
                                                <input type="date" name="end_date" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora sfarsit</label>
                                                <input type="time" name="end_time" onclick="this.showPicker?.()" onfocus="this.showPicker?.()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="single-end-time" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora sfarsit</label>
                                                <input type="time" name="end_time_single" onclick="this.showPicker?.()" onfocus="this.showPicker?.()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora deschidere usi</label>
                                                <input type="time" name="door_time" onclick="this.showPicker?.()" onfocus="this.showPicker?.()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                                <p class="mt-1 text-xs text-ink-soft">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="range-door-time" class="hidden">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora deschidere usi</label>
                                                <input type="time" name="door_time_range" onclick="this.showPicker?.()" onfocus="this.showPicker?.()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                                <p class="mt-1 text-xs text-ink-soft">Ora la care se deschid usile</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="duration-mode-hint" class="text-sm italic text-ink-soft">
                                    Selecteaza tipul duratei pentru a configura programul.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 3: Locatie ============ -->
                    <div id="step-3" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="3">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(3)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">3</div>
                                <div>
                                    <h3 class="font-display font-bold">Locatie</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-3"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-3">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Nume locatie / sala <span class="text-vermilion">*</span></label>
                                    <div class="relative">
                                        <input type="text" name="venue_name" required id="venue-search-input" autocomplete="off" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Cauta sau scrie numele locatiei...">
                                        <div id="venue-dropdown" class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-xl border-2 border-ink bg-paper shadow-deep"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-ink-soft">Cauta in biblioteca de locatii sau scrie manual</p>
                                    <div id="venue-suggestion-notice" class="mt-2 hidden rounded-lg border-2 border-ochre/30 bg-ochre/10 p-3">
                                        <div class="flex items-start gap-2">
                                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-ochre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <p class="text-sm text-ochre">Aceasta locatie nu exista in biblioteca noastra. Numele introdus va fi trimis ca sugestie catre administratorul platformei.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Oras <span class="text-vermilion">*</span></label>
                                        <input type="text" name="venue_city" required id="venue-city-input" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Bucuresti">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Adresa</label>
                                        <input type="text" name="venue_address" id="venue-address-input" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Str. Lipscani nr. 10">
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Website activitate</label>
                                        <input type="url" name="website_url" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Link Facebook</label>
                                        <input type="url" name="facebook_url" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="https://facebook.com/events/...">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Videoclip YouTube</label>
                                    <input type="url" name="video_url" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="https://www.youtube.com/watch?v=...">
                                    <p class="mt-1 text-xs text-ink-soft">Orice link YouTube (watch, share sau embed). Va fi afișat ca videoclip pe pagina publică a activității.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 4: Continut ============ -->
                    <div id="step-4" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="4">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(4)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">4</div>
                                <div>
                                    <h3 class="font-display font-bold">Continut & Descriere</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-4"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-4">
                            <div class="space-y-6 border-t-2 border-ink/10 pt-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Descriere completa</label>
                                    <textarea name="description" id="description-editor"></textarea>
                                    <p class="mt-1 text-xs text-ink-soft">Descrie activitatea in detaliu: program, reguli de acces, etc.</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Conditii activitate</label>
                                    <textarea name="ticket_terms" id="ticket-terms-editor"></textarea>
                                    <p class="mt-1 text-xs text-ink-soft">Conditii de participare, restrictii de varsta, reguli speciale, politica de retur, etc.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 5: Media ============ -->
                    <div id="step-5" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="5">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(5)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">5</div>
                                <div>
                                    <h3 class="font-display font-bold">Media</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-5"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-5">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Poster (vertical)</label>
                                        <div class="relative">
                                            <div id="poster-preview" class="mb-3 hidden">
                                                <img id="poster-img" src="" alt="Poster" class="w-full max-w-[200px] rounded-xl border-2 border-ink/15">
                                                <button type="button" onclick="removePoster()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-vermilion text-paper transition hover:bg-vermilion-d">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="drop-zone flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-ink/30 transition-colors hover:border-vermilion hover:bg-vermilion/5" id="poster-upload-area" data-target="poster">
                                                <svg class="mb-2 h-8 w-8 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-ink-soft">Trage imaginea aici sau click pentru upload</span>
                                                <span class="mt-1 text-xs text-ink-soft">JPG, PNG (recomandat 800x1200, max 5MB)</span>
                                                <input type="file" name="poster" accept="image/*" class="hidden" onchange="previewPoster(this)">
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Imagine cover (orizontala)</label>
                                        <div class="relative">
                                            <div id="cover-preview" class="mb-3 hidden">
                                                <img id="cover-img" src="" alt="Cover" class="w-full rounded-xl border-2 border-ink/15">
                                                <button type="button" onclick="removeCover()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-vermilion text-paper transition hover:bg-vermilion-d">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <label class="drop-zone flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-ink/30 transition-colors hover:border-vermilion hover:bg-vermilion/5" id="cover-upload-area" data-target="cover">
                                                <svg class="mb-2 h-8 w-8 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-sm text-ink-soft">Trage imaginea aici sau click pentru upload</span>
                                                <span class="mt-1 text-xs text-ink-soft">JPG, PNG (recomandat 1200x630, max 5MB)</span>
                                                <input type="file" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 6: Bilete ============ -->
                    <div id="step-6" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="6">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(6)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">6</div>
                                <div>
                                    <h3 class="font-display font-bold">Bilete</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-6"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-6">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <p class="text-sm text-ink-soft">Adauga cel putin un tip de bilet. Poti adauga mai multe categorii (ex: Early Bird, Standard, VIP).</p>

                                <div id="ticket-types-container" class="space-y-4">
                                    <div class="ticket-type-item rounded-xl border-2 border-ink/15 p-4" data-index="0">
                                        <div class="mb-3 flex items-center justify-between">
                                            <h4 class="text-sm font-bold">Tip bilet #1</h4>
                                            <button type="button" onclick="removeTicketType(this)" class="remove-ticket-btn hidden text-vermilion transition hover:text-vermilion-d">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Nume bilet <span class="text-vermilion">*</span></label>
                                                <input type="text" name="ticket_name_0" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Standard, VIP, Early Bird">
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Pret (RON) <span class="text-vermilion">*</span></label>
                                                <input type="number" name="ticket_price_0" required step="0.01" min="0" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="0.00">
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Stoc bilete</label>
                                                <input type="number" name="ticket_quantity_0" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Nelimitat">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Descriere bilet</label>
                                            <input type="text" name="ticket_desc_0" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Acces general, loc nenumerotat">
                                        </div>
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Min. bilete/comanda</label>
                                                <input type="number" name="ticket_min_0" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="1">
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Max. bilete/comanda</label>
                                                <input type="number" name="ticket_max_0" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="10">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" onclick="addTicketType()" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ink/30 py-3 text-sm font-bold text-ink-soft transition-colors hover:border-vermilion hover:text-vermilion">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Adauga alt tip de bilet
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ============ STEP 7: Setari vanzari ============ -->
                    <div id="step-7" class="accordion-section overflow-hidden rounded-2xl border-2 border-ink bg-paper" data-step="7">
                        <button type="button" class="accordion-header flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-paper-2" onclick="toggleAccordion(7)">
                            <div class="flex items-center gap-3">
                                <div class="step-indicator grid h-8 w-8 place-items-center rounded-full bg-ink/10 text-sm font-bold text-ink-soft">7</div>
                                <div>
                                    <h3 class="font-display font-bold">Setari vanzari</h3>
                                    <p class="accordion-summary mt-0.5 text-xs text-ink-soft" id="summary-7"></p>
                                </div>
                            </div>
                            <svg class="accordion-chevron h-5 w-5 text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="accordion-body hidden px-5 pb-5" id="accordion-body-7">
                            <div class="space-y-4 border-t-2 border-ink/10 pt-2">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Capacitate totala</label>
                                        <input type="number" name="capacity" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: 500">
                                        <p class="mt-1 text-xs text-ink-soft">Numarul total de locuri disponibile</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Max. bilete per comanda</label>
                                        <input type="number" name="max_tickets_per_order" min="1" max="50" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="10">
                                        <p class="mt-1 text-xs text-ink-soft">Cate bilete poate cumpara un client intr-o comanda</p>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Inceput vanzari</label>
                                        <input type="datetime-local" name="sales_start_at" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                        <p class="mt-1 text-xs text-ink-soft">Cand incep vanzarile (gol = imediat)</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-ink-soft">Sfarsit vanzari</label>
                                        <input type="datetime-local" name="sales_end_at" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                        <p class="mt-1 text-xs text-ink-soft">Cand se opresc vanzarile (gol = la inceput activitate)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Actions -->
                    <div class="flex items-center justify-between pt-4">
                        <button type="button" onclick="hideCreateForm()" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Inapoi la activități
                        </button>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="saveEventDraft()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Salveaza ciorna
                            </button>
                            <button type="button" onclick="saveAndSubmitEvent()" id="submit-review-btn" class="inline-flex items-center gap-2 rounded-full bg-forest px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-forest/90">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Salveaza si trimite spre aprobare
                            </button>
                        </div>
                    </div>
                </form>
            </div><!-- /grid lg:grid-cols-[260px_1fr] -->

            <!-- ============ LIVE PREVIEW DRAWER (right side) ============ -->
            <div id="live-preview-backdrop" class="fixed inset-0 z-40 hidden bg-ink/40 transition-opacity" onclick="closeLivePreview()"></div>
            <aside id="live-preview-drawer" class="fixed inset-y-0 right-0 z-50 hidden w-full flex-col border-l-2 border-ink bg-paper shadow-deep sm:max-w-md flex" aria-hidden="true">
                <div class="flex items-center justify-between border-b-2 border-ink/15 bg-paper-2 px-5 py-4">
                    <div>
                        <h3 class="font-display font-bold">Live preview</h3>
                        <p class="mt-0.5 text-xs text-ink-soft">Cum va apărea activitatea publicului</p>
                    </div>
                    <button type="button" onclick="closeLivePreview()" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
                </div>
                <div id="live-preview-body" class="flex-1 space-y-4 overflow-y-auto p-5"></div>
                <div class="border-t-2 border-ink/15 bg-paper-2 px-5 py-3">
                    <a id="live-preview-public-link" href="#" target="_blank" class="hidden items-center gap-1.5 text-xs font-bold text-vermilion hover:underline inline-flex">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Deschide pagina publică
                    </a>
                </div>
            </aside>

            <!-- ============ MOBILE STICKY ACTION BAR ============ -->
            <div id="mobile-action-bar" class="sticky bottom-0 left-0 right-0 z-30 -mx-4 mt-6 flex items-center gap-2 border-t-2 border-ink/15 bg-paper px-4 py-3 shadow-deep lg:hidden">
                <button type="button" onclick="hideCreateForm()" class="flex-shrink-0 grid h-10 w-10 place-items-center rounded-full border-2 border-ink transition hover:bg-ink hover:text-paper" title="Înapoi">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </button>
                <button type="button" onclick="saveEventDraft()" class="flex flex-1 items-center justify-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Salvează ciornă
                </button>
                <button type="button" onclick="saveAndSubmitEvent()" id="mobile-submit-btn" class="hidden flex-1 items-center justify-center gap-2 rounded-full bg-forest px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-forest/90 flex">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Trimite
                </button>
            </div>
        </div>

        <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
    </main>
</div>

<!-- Postponed Modal -->
<div id="postponed-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-display text-xl font-bold">Amână activitatea</h3>
            <button type="button" onclick="closePostponedModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <form id="postponed-form" onsubmit="savePostponed(event)" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Noua dată a activității</label>
                <input type="date" name="postponed_date" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora start</label>
                    <input type="time" name="postponed_start_time" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora deschidere</label>
                    <input type="time" name="postponed_door_time" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Ora sfârșit</label>
                    <input type="time" name="postponed_end_time" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Motivul amânării</label>
                <textarea name="postponed_reason" rows="3" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Ex: Din motive tehnice, activitatea a fost amânată..."></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closePostponedModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button>
                <button type="submit" class="rounded-full bg-ochre px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-ochre/90">Marchează ca amânat</button>
            </div>
        </form>
    </div>
</div>

<!-- Cancelled Modal -->
<div id="cancelled-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-display text-xl font-bold">Anulează activitatea</h3>
            <button type="button" onclick="closeCancelledModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <div class="mb-4 rounded-xl bg-vermilion/10 p-4 text-sm text-vermilion">
            <strong>Atenție!</strong> Anularea activității va notifica toți participanții și nu poate fi anulată.
        </div>
        <form id="cancelled-form" onsubmit="saveCancelled(event)" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Motivul anulării <span class="text-vermilion">*</span></label>
                <textarea name="cancel_reason" rows="3" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Ex: Din cauza condițiilor meteo, activitatea a fost anulată..."></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeCancelledModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Înapoi</button>
                <button type="submit" class="rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Confirmă anularea</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Status pills (list filters) */
    .status-pill { padding: 6px 16px; border-radius: 9999px; font-size: 13px; font-weight: 700; border: 2px solid rgba(27,23,20,.15); background: #F5EFE6; color: #5A4F46; cursor: pointer; transition: all .15s; }
    .status-pill:hover { border-color: #1B1714; color: #1B1714; }
    .status-pill.active { background: #1E4A3D; color: #F5EFE6; border-color: #1E4A3D; }
    .pill-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; margin-left: 6px; border-radius: 9999px; font-size: 11px; font-weight: 700; background: rgba(27,23,20,.1); color: #5A4F46; }
    .status-pill.active .pill-count { background: rgba(245,239,230,0.25); color: #F5EFE6; }

    .accordion-body.hidden { display: none; }
    .accordion-section[data-open="true"] .accordion-chevron { transform: rotate(180deg); }
    .accordion-section[data-open="true"] .step-indicator { background-color: #E84527; color: #F5EFE6; }
    .step-indicator.completed { background-color: #1E4A3D !important; color: #F5EFE6 !important; }
    .duration-mode-option:has(input:checked) { border-color: #E84527; background-color: rgba(232,69,39,0.06); }

    /* Multiselect (genres / artists) */
    .multiselect-wrapper { position: relative; border: 2px solid rgba(27,23,20,.15); border-radius: 0.75rem; padding: 6px 10px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; min-height: 42px; background: #EFE7DA; cursor: text; }
    .multiselect-wrapper:focus-within { border-color: #1B1714; }
    .multiselect-tags { display: contents; }
    .multiselect-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background-color: #E84527; color: #F5EFE6; white-space: nowrap; }
    .multiselect-tag button { background: none; border: none; color: #F5EFE6; cursor: pointer; font-size: 14px; line-height: 1; padding: 0 2px; opacity: 0.8; }
    .multiselect-tag button:hover { opacity: 1; }
    .multiselect-input { border: none; outline: none; flex: 1; min-width: 80px; font-size: 0.875rem; padding: 2px 4px; background: transparent; }
    .multiselect-dropdown { position: absolute; z-index: 50; left: 0; right: 0; top: 100%; margin-top: 4px; background: #F5EFE6; border: 2px solid #1B1714; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(27,23,20,0.2); max-height: 200px; overflow-y: auto; }
    .multiselect-option { padding: 8px 12px; cursor: pointer; font-size: 0.875rem; transition: background 0.1s; }
    .multiselect-option:hover { background-color: #EFE7DA; }
    .multiselect-option.create-new { color: #E84527; font-weight: 700; border-top: 2px solid rgba(27,23,20,.1); }

    /* Venue dropdown options */
    .venue-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(27,23,20,.08); transition: background 0.1s; }
    .venue-option:hover { background-color: #EFE7DA; }
    .venue-option.bg-amber-50 { background-color: rgba(218,154,51,0.1); }
    .venue-option.bg-amber-50:hover { background-color: rgba(218,154,51,0.2); }
    .venue-option:last-child { border-bottom: none; }

    /* TinyMCE polish */
    .tox-tinymce { border-radius: 0.75rem !important; border: 2px solid rgba(27,23,20,.15) !important; }
    .tox .tox-edit-area__iframe { min-height: 160px; }

    /* Sticky header shadow when scrolled */
    #event-edit-header { transition: box-shadow 0.2s; }
    #event-edit-header.is-scrolled { box-shadow: 0 4px 16px -4px rgba(27,23,20,0.15); }

    /* Status badge variants (sticky header) */
    #header-event-status.status-published { background: rgba(30,74,61,0.15); color: #1E4A3D; } #header-event-status.status-published .status-dot { background: #1E4A3D; }
    #header-event-status.status-draft     { background: rgba(27,23,20,0.1);  color: #5A4F46; } #header-event-status.status-draft     .status-dot { background: #5A4F46; }
    #header-event-status.status-pending   { background: rgba(218,154,51,0.15); color: #DA9A33; } #header-event-status.status-pending   .status-dot { background: #DA9A33; }
    #header-event-status.status-rejected  { background: rgba(232,69,39,0.15); color: #E84527; } #header-event-status.status-rejected  .status-dot { background: #E84527; }
    #header-event-status.status-cancelled { background: rgba(232,69,39,0.15); color: #E84527; } #header-event-status.status-cancelled .status-dot { background: #E84527; }
    #header-event-status.status-postponed { background: rgba(218,154,51,0.2);  color: #DA9A33; } #header-event-status.status-postponed .status-dot { background: #DA9A33; }
    #header-event-status.status-soldout   { background: rgba(44,95,138,0.15); color: #2C5F8A; } #header-event-status.status-soldout   .status-dot { background: #2C5F8A; }
    #header-event-status.status-ended     { background: rgba(27,23,20,0.1);  color: #5A4F46; } #header-event-status.status-ended     .status-dot { background: #5A4F46; }

    /* Outline sidebar items */
    .outline-item { display: flex; align-items: center; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; color: #5A4F46; transition: background 0.12s, color 0.12s; text-decoration: none; }
    .outline-item:hover { background: #EFE7DA; color: #1B1714; }
    .outline-item.is-active { background: rgba(232,69,39,0.08); color: #E84527; font-weight: 700; }
    .outline-item.is-active svg { color: #E84527; }
    .outline-item svg { color: #5A4F46; flex-shrink: 0; }
    .outline-item.is-active svg, .outline-item:hover svg { color: inherit; }
    .outline-status { display: inline-flex; align-items: center; justify-content: center; width: 1rem; height: 1rem; border-radius: 9999px; flex-shrink: 0; font-size: 9px; }
    .outline-item .outline-status.complete { background: #1E4A3D; color: #F5EFE6; }
    .outline-item .outline-status.complete::before { content: '✓'; font-weight: 700; }
    .outline-item .outline-status.partial { background: rgba(27,23,20,.2); }
    .outline-item .outline-status.partial::before { content: ''; width: 5px; height: 5px; background: #5A4F46; border-radius: 50%; display: block; }
    .outline-item .outline-status.required { background: rgba(232,69,39,0.15); color: #E84527; }
    .outline-item .outline-status.required::before { content: '!'; font-weight: 700; }

    /* Desktop: cards polished, accordion always open */
    @media (min-width: 1024px) {
        .accordion-section { transition: box-shadow 0.15s, border-color 0.15s; scroll-margin-top: 200px; }
        .accordion-section.is-active-section { box-shadow: 0 4px 16px -4px rgba(232,69,39,0.25); }
        .accordion-body { display: block !important; }
        .accordion-section .accordion-header { cursor: default; }
        .accordion-section .accordion-chevron { display: none; }
        .accordion-section .accordion-header:hover { background: transparent !important; }
    }

    .step-indicator { transition: background 0.15s, color 0.15s; }

    /* Live preview drawer animation */
    #live-preview-drawer { transform: translateX(100%); transition: transform 0.25s ease-out; }
    #live-preview-drawer.is-open { transform: translateX(0); }
    #live-preview-backdrop { opacity: 0; transition: opacity 0.2s; }
    #live-preview-backdrop.is-open { opacity: 1; }

    /* Skeleton shimmer */
    .edit-skeleton { background: linear-gradient(90deg, #EFE7DA 0%, #E0D6C5 50%, #EFE7DA 100%); background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 0.5rem; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    #mobile-action-bar { box-shadow: 0 -4px 12px -4px rgba(27,23,20,0.1); }
</style>

<?php
$scriptsExtra  = '<script>const MARKETPLACE_NAME = ' . json_encode(SITE_NAME) . ';</script>';
$scriptsExtra .= <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth) BileteOnlineAuth.requireOrganizerAuth();
});

let ticketTypeCount = 1;
let descriptionEditor = null;
let ticketTermsEditor = null;
// Content queued to be applied as soon as the TinyMCE editors finish init.
let pendingDescription = null;
let pendingTicketTerms = null;
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

document.addEventListener('DOMContentLoaded', function() {
    if (urlParams.get('action') === 'create') {
        showCreateForm();
    } else if (eventId) {
        loadEventForEdit(eventId);
    } else {
        loadEvents();
    }
});

// ==================== ACTIVITIES LIST ====================

let allEventsCache = []; // Cache for instant search

async function loadEvents() {
    try {
        // Fetch all events (API defaults to 20 per_page)
        let allEvents = [];
        let page = 1;
        while (true) {
            const response = await BileteOnlineAPI.organizer.getEvents({ per_page: 50, page });
            // Robust to all core response shapes: data:[...] | data:{data:[...]} (paginator) | data:{events|items:[...]}
            const _d = response && response.data;
            const events = Array.isArray(_d) ? _d : (_d && (Array.isArray(_d.data) ? _d.data : (_d.events || _d.items))) || (Array.isArray(response) ? response : []);
            allEvents = allEvents.concat(events);
            if (events.length < 50) break;
            page++;
        }
        const events = allEvents;
        allEventsCache = events; // Cache for instant filtering
        updatePillCounts(events);

        // Update sidebar events count
        const activeEvents = events.filter(e => e.status === 'published' || e.status === 'active').length;
        const navCount = document.getElementById('nav-events-count');
        if (navCount) navCount.textContent = activeEvents || events.length;

        // Apply current filters (status + search)
        applyFilters();
    } catch (error) {
        document.getElementById('events-list').classList.add('hidden');
        document.getElementById('no-events').classList.remove('hidden');
    }
}

let currentStatusFilter = 'ongoing'; // Default: show ongoing events

function updatePillCounts(events) {
    const counts = { ongoing: 0, draft: 0, ended: 0 };
    events.forEach(e => {
        const s = getEventDisplayStatus(e);
        if (s === 'ongoing') counts.ongoing++;
        else if (s === 'draft') counts.draft++;
        else counts.ended++; // ended, cancelled, postponed
    });
    const labels = { ongoing: 'În derulare', draft: 'Ciorne', ended: 'Încheiate', '': 'Toate' };
    document.querySelectorAll('.status-pill').forEach(pill => {
        const status = pill.dataset.status;
        const count = status === '' ? events.length : (counts[status] || 0);
        pill.innerHTML = `${labels[status]} <span class="pill-count">${count}</span>`;
    });
}

function getEventDisplayStatus(event) {
    const eventEndDate = event.ends_at || event.starts_at;
    const isEnded = event.status === 'ended' || event.is_past || event.is_ended ||
        (eventEndDate && new Date(eventEndDate) < new Date());
    if (event.is_cancelled || event.status === 'cancelled') return 'cancelled';
    if (event.is_postponed || event.status === 'postponed') return 'postponed';
    if (isEnded) return 'ended';
    if (event.status === 'draft' || event.status === 'pending_review') return 'draft';
    return 'ongoing';
}

function filterEvents(events, query) {
    let filtered = events;

    // Status filter
    if (currentStatusFilter === 'ongoing') {
        filtered = filtered.filter(e => getEventDisplayStatus(e) === 'ongoing');
    } else if (currentStatusFilter === 'draft') {
        filtered = filtered.filter(e => getEventDisplayStatus(e) === 'draft');
    } else if (currentStatusFilter === 'ended') {
        filtered = filtered.filter(e => ['ended', 'cancelled', 'postponed'].includes(getEventDisplayStatus(e)));
    }
    // '' = all, no status filter

    // Text search
    if (query) {
        filtered = filtered.filter(event => {
            const name = (event.name || event.title || '').toLowerCase();
            const venue = (event.venue_name || '').toLowerCase();
            const city = (event.venue_city || '').toLowerCase();
            return name.includes(query) || venue.includes(query) || city.includes(query);
        });
    }

    return filtered;
}

function applyFilters() {
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

// Initialize filters
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('search-input')?.addEventListener('input', BileteOnlineUtils.debounce(applyFilters, 150));

    // Status pill clicks
    document.querySelectorAll('.status-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentStatusFilter = this.dataset.status;
            applyFilters();
        });
    });
});

// Fixed v3 class-maps (no runtime-dynamic Tailwind classes)
const STATUS_BADGE_CLASSES = {
    published:     'bg-forest/15 text-forest',
    draft:         'bg-ochre/15 text-ochre',
    ended:         'bg-ink/10 text-ink-soft',
    pending_review:'bg-sky/15 text-sky',
    rejected:      'bg-vermilion/15 text-vermilion',
    cancelled:     'bg-vermilion/15 text-vermilion',
    postponed:     'bg-ochre/15 text-ochre',
    sold_out:      'bg-sky/15 text-sky'
};
const SALE_BADGE_CLASSES = {
    'În vânzare': 'bg-forest/15 text-forest',
    'Sold Out':   'bg-sky/15 text-sky',
    'Door Sales': 'bg-ochre/15 text-ochre'
};
function statusBadgeClass(s) { return STATUS_BADGE_CLASSES[s] || 'bg-ink/10 text-ink-soft'; }
function saleBadgeClass(s)   { return SALE_BADGE_CLASSES[s] || 'bg-ochre/15 text-ochre'; }

function renderEvents(events) {
    const container = document.getElementById('events-list');
    const statusLabels = { published: 'Publicat', draft: 'Ciornă', ended: 'Activitate încheiată', pending_review: 'În revizie - așteaptă aprobare', rejected: 'Respins', cancelled: 'Anulat', postponed: 'Amânat', sold_out: 'Sold Out' };

    // Sort: ongoing events by closest date first, ended by most recent first
    events = [...events].sort((a, b) => {
        const aStatus = getEventDisplayStatus(a), bStatus = getEventDisplayStatus(b);
        const aOngoing = aStatus === 'ongoing', bOngoing = bStatus === 'ongoing';
        if (aOngoing && bOngoing) {
            return new Date(a.starts_at || 0) - new Date(b.starts_at || 0);
        }
        return new Date(b.starts_at || 0) - new Date(a.starts_at || 0);
    });

    container.innerHTML = events.map(event => {
        const eventEndDate = event.ends_at || event.starts_at;
        const isEnded = event.status === 'ended' || event.is_past || event.is_ended ||
            (eventEndDate && new Date(eventEndDate) < new Date());

        const isOngoing = event.status === 'published' && !isEnded && !event.is_cancelled && !event.is_postponed;

        let displayStatus = event.status;
        let saleStatus = '';
        if (event.is_cancelled || event.status === 'cancelled') displayStatus = 'cancelled';
        else if (event.is_postponed || event.status === 'postponed') displayStatus = 'postponed';
        else if (event.is_sold_out) { displayStatus = 'published'; saleStatus = 'Sold Out'; }
        else if (event.is_door_sales_only) { displayStatus = 'published'; saleStatus = 'Door Sales'; }
        else if (isEnded) displayStatus = 'ended';
        else if (isOngoing) saleStatus = 'În vânzare';

        const daysUntil = event.days_until;
        let daysText = '';
        if (daysUntil !== null && daysUntil !== undefined && !isEnded) {
            if (daysUntil === 0) daysText = 'Azi';
            else if (daysUntil === 1) daysText = 'Mâine';
            else if (daysUntil > 0) daysText = `${daysUntil}`;
        }

        const isAwaitingApproval = ['draft', 'pending_review', 'rejected'].includes(event.status);

        // Action button class-maps (fixed v3 styling)
        const subBtnBase = 'inline-flex items-center gap-1.5 rounded-full border-2 px-3 py-1.5 text-xs font-bold transition';
        const editBtnCls = subBtnBase + ' border-sky/40 bg-sky/10 text-sky hover:bg-sky/20';
        const analyticsBtnCls = subBtnBase + ' border-sky/40 bg-sky/10 text-sky hover:bg-sky/20';
        const documentsBtnCls = subBtnBase + ' border-ink/20 bg-paper-2 text-ink-soft hover:bg-ink/10';
        const financeBtnCls = subBtnBase + ' border-forest/40 bg-forest/10 text-forest hover:bg-forest/20';
        const staffBtnCls = subBtnBase + ' border-forest/40 bg-forest/10 text-forest hover:bg-forest/20';
        const participantsBtnCls = subBtnBase + ' border-sky/40 bg-sky/10 text-sky hover:bg-sky/20';
        const invitationsBtnCls = subBtnBase + ' border-vermilion/40 bg-vermilion/10 text-vermilion hover:bg-vermilion/20';
        const viewBtnCls = subBtnBase + ' border-ink hover:bg-ink hover:text-paper';
        const deleteBtnCls = 'inline-flex items-center gap-1.5 rounded-full bg-vermilion px-3 py-1.5 text-xs font-bold text-paper transition hover:bg-vermilion-d';
        const promoteBtnCls = 'inline-flex items-center gap-1.5 rounded-full bg-vermilion px-3 py-1.5 text-xs font-bold text-paper transition hover:bg-vermilion-d';

        const analyticsButton = isAwaitingApproval ? '' : (isEnded
            ? `<a href="/organizator/report/${event.id}" class="${analyticsBtnCls}" title="Raport"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Raport</a>`
            : `<a href="/organizator/analytics/${event.id}" class="${analyticsBtnCls}" title="Analiză"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg> Analiză</a>`);

        const promoteButton = isOngoing
            ? `<a href="/organizator/servicii?event=${event.id}" class="${promoteBtnCls}" title="Promovează"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>Promovează</a>`
            : '';

        const documentsButton = isAwaitingApproval ? '' : `<a href="/organizator/documente?event=${event.id}" class="${documentsBtnCls}" title="Documente"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Documente</a>`;

        const financeButton = isAwaitingApproval ? '' : `<a href="/organizator/sold?event=${event.id}" class="${financeBtnCls}" title="Finanțe"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Vânzări</a>`;

        const staffReportButton = (isAwaitingApproval || !isEnded) ? '' : `<a href="/organizator/raport-staff?event=${event.id}" class="${staffBtnCls}" title="Raport staff"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg> Raport staff</a>`;

        const participantsButton = isAwaitingApproval ? '' : `<a href="/organizator/participanti?event=${event.id}" class="${participantsBtnCls}" title="Participanți"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Participanți</a>`;

        const invitationsButton = (isAwaitingApproval || isEnded) ? '' : `<a href="/organizator/invitatii?event=${event.id}" class="${invitationsBtnCls}" title="Generează invitații"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Invitații</a>`;

        const isPublishedEvent = event.status === 'published' || event.is_public;
        const viewUrl = isPublishedEvent ? `/bilete/${event.slug}` : `/bilete/${event.slug}?preview=1`;
        const viewButton = !isEnded
            ? `<a href="${viewUrl}" target="_blank" class="${viewBtnCls}" title="${isPublishedEvent ? 'Vizualizează' : 'Previzualizare'}"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>Pagină <span class="max-md:hidden">activitate</span></a>`
            : '';

        const fmtDate = (d) => { try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d || ''; } };

        return `
        <div class="rounded-2xl border-2 border-ink bg-paper transition-colors hover:border-vermilion">
            <div class="flex flex-col md:flex-row md:items-center">
                <img src="${getStorageUrl(event.image)}" alt="${event.name || event.title}" class="h-34 max-h-[115px] w-full rounded-xl rounded-br-none rounded-tr-none object-cover md:w-28" loading="lazy">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-4 pr-4 max-md:flex-col max-md:px-4 max-md:py-2">
                        <div class="flex items-center gap-4 pl-6 max-md:pl-0">
                            <div class="flex flex-col items-start">
                                <h3 class="font-display text-lg font-bold">${event.name || event.title}</h3>
                                <div class="flex flex-wrap items-center gap-3 text-sm text-ink-soft">
                                    <span class="flex items-center gap-1"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${event.starts_at ? fmtDate(event.starts_at) : (event.start_date ? fmtDate(event.start_date) : '')}</span>
                                    <span class="flex items-center gap-1"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>${[event.venue_name, event.venue_city].filter(Boolean).join(', ') || ''}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="py-2 pr-4 text-right max-md:text-center"><p class="font-display text-2xl font-bold">${event.views || 0}</p><p class="text-xs text-ink-soft">Vizualizări</p></div>
                            <div class="border-r-2 border-ink/10 py-2 pr-4 text-right max-md:border-l max-md:px-4 max-md:text-center"><p class="font-display text-2xl font-bold">${event.tickets_sold || 0}</p><p class="text-xs text-ink-soft">Bilete vândute</p></div>
                            <div class="py-2 text-right max-md:text-center"><p class="font-display text-2xl font-bold">${BileteOnlineUtils.formatCurrency(event.revenue || 0)}</p><p class="text-xs text-ink-soft">Încasări nete</p></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t-2 border-ink/10 py-2 pl-6 pr-2 max-md:px-2">
                        <div class="ml-0 mr-auto flex items-center gap-x-4 max-md:mx-auto">
                            <div class="flex items-center justify-center gap-x-2"><p class="font-display text-3xl font-bold">${daysText || ''}</p><p class="text-xs text-ink-soft" style="line-height:0.85rem;">${(!isEnded && daysText && daysText !== 'Azi' && daysText !== 'Mâine') ? 'zile<br/>rămase' : ''}</p></div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold ${statusBadgeClass(displayStatus)}">${statusLabels[displayStatus] || displayStatus}</span>
                                ${saleStatus ? `<span class="rounded-full px-2.5 py-1 text-xs font-bold ${saleBadgeClass(saleStatus)}">${saleStatus}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 max-md:grid max-md:grid-cols-3">
                            ${event.is_editable !== false ? `<a href="/organizator/event/${event.id}?action=edit" class="${editBtnCls}" title="Editează"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Editează</a>` : ''}
                            ${invitationsButton}
                            ${documentsButton}
                            ${financeButton}
                            ${staffReportButton}
                            ${participantsButton}
                            ${analyticsButton}
                            ${promoteButton}
                            ${viewButton}
                            ${['draft', 'pending_review', 'rejected'].includes(event.status) ? `<button onclick="deleteEvent(${event.id}, '${(event.name || event.title).replace(/'/g, "\\'")}');" class="${deleteBtnCls}" title="Șterge activitatea"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}

async function deleteEvent(eventId, eventName) {
    if (!confirm(`Ești sigur că vrei să ștergi activitatea "${eventName}"?\n\nAceasta acțiune este ireversibilă.`)) {
        return;
    }

    try {
        orgNotify('Se șterge activitatea...', 'info');
        const response = await BileteOnlineAPI.delete(`/organizer/events/${eventId}`);

        if (response.success) {
            orgNotify('Activitatea a fost ștearsă cu succes', 'success');
            loadEvents();
        } else {
            orgNotify(response.message || 'Eroare la ștergerea activității', 'error');
        }
    } catch (error) {
        console.error('Delete event error:', error);
        orgNotify(error.message || 'Eroare la ștergerea activității', 'error');
    }
}

// ==================== CREATE FORM ====================

function showCreateForm() {
    document.getElementById('events-view').classList.add('hidden');
    document.getElementById('create-event-view').classList.remove('hidden');
    history.pushState({}, '', '/organizator/events?action=create');
    toggleAccordion(1);
    loadCategories();

    resetFormState();
    initEditors();
    initVenueSearch();
    initArtistSearch();
    initGenreSearch();
    initDragDrop();
    initShortDescWordCount();
}

function resetFormState() {
    selectedGenres = [];
    selectedArtists = [];
    renderGenreTags();
    renderArtistTags();

    document.getElementById('rejection-banner')?.classList.add('hidden');
    document.getElementById('edit-delete-btn')?.classList.add('hidden');

    const posterPreview = document.getElementById('poster-preview');
    const posterUploadArea = document.getElementById('poster-upload-area');
    const coverPreview = document.getElementById('cover-preview');
    const coverUploadArea = document.getElementById('cover-upload-area');

    if (posterPreview) posterPreview.classList.add('hidden');
    if (posterUploadArea) posterUploadArea.classList.remove('hidden');
    if (coverPreview) coverPreview.classList.add('hidden');
    if (coverUploadArea) coverUploadArea.classList.remove('hidden');

    const posterInput = document.querySelector('[name="poster"]');
    const coverInput = document.querySelector('[name="cover_image"]');
    if (posterInput) posterInput.value = '';
    if (coverInput) coverInput.value = '';

    if (window.tinymce) {
        try { tinymce.get('description-editor')?.remove(); } catch (e) {}
        try { tinymce.get('ticket-terms-editor')?.remove(); } catch (e) {}
    }
    descriptionEditor = null;
    ticketTermsEditor = null;

    const form = document.getElementById('create-event-form');
    if (form) {
        form.reset();
        form.querySelectorAll('input, textarea, select').forEach(el => {
            if (el.type === 'hidden') return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = el.defaultChecked;
            } else if (el.type === 'file') {
                el.value = '';
            } else {
                el.value = '';
                el.setAttribute('value', '');
            }
        });
        const ttContainer = document.getElementById('ticket-types-container');
        if (ttContainer) {
            ttContainer.innerHTML = '';
            ticketTypeCount = 0;
            if (typeof addTicketType === 'function') addTicketType();
            const firstRemove = ttContainer.querySelector('.remove-ticket-btn');
            if (firstRemove) firstRemove.classList.add('hidden');
        }
        const perfList = document.getElementById('performances-list');
        if (perfList) perfList.innerHTML = '';
    }

    const savedIdEl = document.getElementById('saved-event-id');
    if (savedIdEl) savedIdEl.value = '';

    const singleDayRadio = document.querySelector('[name="duration_mode"][value="single_day"]');
    if (singleDayRadio) {
        singleDayRadio.checked = true;
        if (typeof onDurationModeChange === 'function') onDurationModeChange('single_day');
    }
}

async function loadEventForEdit(eventId) {
    try {
        const response = await BileteOnlineAPI.organizer.getEvent(eventId);
        const event = response.data?.event || response.event || response.data || response;

        if (!event || !event.id) {
            orgNotify('Activitatea nu a fost găsită.', 'error');
            window.location.href = '/organizator/events';
            return;
        }

        if (event.is_editable === false || event.is_past === true) {
            orgNotify('Această activitate este încheiată și nu mai poate fi editată.', 'error');
            window.location.href = '/organizator/events';
            return;
        }

        showCreateForm();

        history.replaceState({}, '', `/organizator/event/${eventId}?action=edit`);

        const titleEl = document.querySelector('#create-event-view h1');
        if (titleEl) titleEl.textContent = event.name || event.title || 'Editare activitate';

        if (typeof window.hydrateEventHeader === 'function') {
            try { window.hydrateEventHeader(event); } catch (e) {}
        }

        document.getElementById('saved-event-id').value = eventId;

        const statusActions = document.getElementById('event-status-actions');
        const isAwaitingApprovalState = ['draft', 'pending_review', 'rejected'].includes(event.status);
        if (statusActions) {
            if (isAwaitingApprovalState) {
                statusActions.classList.add('hidden');
            } else {
                statusActions.classList.remove('hidden');
            }
        }

        const editDeleteBtn = document.getElementById('edit-delete-btn');
        if (editDeleteBtn) {
            if (isAwaitingApprovalState) {
                editDeleteBtn.classList.remove('hidden');
                editDeleteBtn.onclick = () => deleteEvent(event.id, event.name || event.title || '');
            } else {
                editDeleteBtn.classList.add('hidden');
            }
        }

        const rejectionBanner = document.getElementById('rejection-banner');
        const rejectionReason = document.getElementById('rejection-reason-text');
        if (rejectionBanner) {
            if (event.status === 'rejected' && event.rejection_reason) {
                if (rejectionReason) rejectionReason.textContent = event.rejection_reason;
                rejectionBanner.classList.remove('hidden');
            } else {
                rejectionBanner.classList.add('hidden');
            }
        }

        currentEventStatus = {
            is_sold_out: event.is_sold_out || false,
            door_sales_only: event.door_sales_only || false,
            is_postponed: event.is_postponed || false,
            is_cancelled: event.is_cancelled || false,
            is_published: event.is_public || event.status === 'published',
            slug: event.slug
        };
        updateStatusIndicators();

        const isPublished = currentEventStatus.is_published;
        const saveDraftBtn = document.getElementById('save-draft-btn');
        const bottomDraftBtns = document.querySelectorAll('button[onclick="saveEventDraft()"]');
        const submitReviewBtn = document.getElementById('submit-review-btn');

        if (isPublished) {
            if (saveDraftBtn) saveDraftBtn.style.display = 'none';
            bottomDraftBtns.forEach(btn => {
                if (btn !== saveDraftBtn) btn.style.display = 'none';
            });
            if (submitReviewBtn) {
                submitReviewBtn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Salvează modificările';
            }
        } else {
            if (saveDraftBtn) saveDraftBtn.style.display = '';
            bottomDraftBtns.forEach(btn => btn.style.display = '');
            if (submitReviewBtn) {
                submitReviewBtn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Salvează și trimite spre aprobare';
            }
        }

        const form = document.getElementById('create-event-form');

        // Step 1: Basic details
        if (event.name) form.querySelector('[name="name"]').value = event.name;
        if (event.short_description) {
            form.querySelector('[name="short_description"]').value = event.short_description;
            const wordCount = event.short_description.trim().split(/\s+/).filter(w => w.length > 0).length;
            const countEl = document.getElementById('short-desc-count');
            if (countEl) countEl.textContent = wordCount;
        }
        if (event.tags) {
            const tagsStr = Array.isArray(event.tags) ? event.tags.join(', ') : event.tags;
            form.querySelector('[name="tags"]').value = tagsStr;
        }

        if (event.marketplace_event_category_id) {
            await loadCategories();
            const catSelect = form.querySelector('[name="marketplace_event_category_id"]');
            if (catSelect) {
                catSelect.value = event.marketplace_event_category_id;
                onCategoryChange(event.marketplace_event_category_id);
            }
        }

        // Step 2: Schedule
        if (event.starts_at) {
            const startDt = new Date(event.starts_at);
            const startDate = startDt.toISOString().split('T')[0];
            const startTime = startDt.toTimeString().slice(0, 5);
            form.querySelector('[name="start_date"]').value = startDate;
            form.querySelector('[name="start_time"]').value = startTime;

            if (event.ends_at) {
                const endDt = new Date(event.ends_at);
                const endDate = endDt.toISOString().split('T')[0];
                const endTime = endDt.toTimeString().slice(0, 5);

                if (endDate === startDate) {
                    const radio = form.querySelector('[name="duration_mode"][value="single_day"]');
                    if (radio) { radio.checked = true; onDurationModeChange('single_day'); }
                    form.querySelector('[name="end_time_single"]').value = endTime;
                } else {
                    const radio = form.querySelector('[name="duration_mode"][value="range"]');
                    if (radio) { radio.checked = true; onDurationModeChange('range'); }
                    form.querySelector('[name="end_date"]').value = endDate;
                    const endTimeInput = form.querySelector('[name="end_time"]');
                    if (endTimeInput) endTimeInput.value = endTime;
                }
            } else {
                const radio = form.querySelector('[name="duration_mode"][value="single_day"]');
                if (radio) { radio.checked = true; onDurationModeChange('single_day'); }
            }

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

        const eventWebsite = event.event_website_url || event.website_url;
        if (eventWebsite) form.querySelector('[name="website_url"]').value = eventWebsite;
        if (event.facebook_url) form.querySelector('[name="facebook_url"]').value = event.facebook_url;
        if (event.video_url) {
            const videoInput = form.querySelector('[name="video_url"]');
            if (videoInput) videoInput.value = event.video_url;
        }

        // Step 4: Content
        if (event.description) {
            if (descriptionEditor) {
                descriptionEditor.setContent(event.description);
            } else {
                pendingDescription = event.description;
            }
        }
        if (event.ticket_terms) {
            if (ticketTermsEditor) {
                ticketTermsEditor.setContent(event.ticket_terms);
            } else {
                pendingTicketTerms = event.ticket_terms;
            }
        }
        updateSummaries();

        // Step 6: Ticket types
        if (event.ticket_types && event.ticket_types.length > 0) {
            const container = document.getElementById('ticket-types-container');
            container.innerHTML = '';
            ticketTypeCount = 0;

            event.ticket_types.forEach((tt, i) => {
                ticketTypeCount = i + 1;
                const removeBtn = i === 0 ? 'hidden' : '';
                container.innerHTML += `
                    <div class="ticket-type-item rounded-xl border-2 border-ink/15 p-4" data-index="${i}">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-bold">Tip bilet #${i + 1}</h4>
                            <button type="button" onclick="removeTicketType(this)" class="${removeBtn} remove-ticket-btn text-vermilion transition hover:text-vermilion-d">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Nume bilet <span class="text-vermilion">*</span></label>
                                <input type="text" name="ticket_name_${i}" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Standard, VIP" value="${tt.name || ''}">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Pret (RON) <span class="text-vermilion">*</span></label>
                                <input type="number" name="ticket_price_${i}" required step="0.01" min="0" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="0.00" value="${tt.price || 0}">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Stoc bilete</label>
                                <input type="number" name="ticket_quantity_${i}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Nelimitat" value="${tt.quantity || ''}">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="mb-1.5 block text-xs font-bold text-ink-soft">Descriere bilet</label>
                            <input type="text" name="ticket_desc_${i}" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Acces general" value="${tt.description || ''}">
                        </div>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Min. bilete/comanda</label>
                                <input type="number" name="ticket_min_${i}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="1" value="${tt.min_per_order || ''}">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Max. bilete/comanda</label>
                                <input type="number" name="ticket_max_${i}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="10" value="${tt.max_per_order || ''}">
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

        if (event.genres && event.genres.length > 0) {
            setTimeout(() => {
                selectedGenres = event.genres.map(g => ({ id: g.id, name: g.name }));
                renderGenreTags();
                const genresContainer = document.getElementById('genres-container');
                if (genresContainer) genresContainer.classList.remove('hidden');
            }, 600);
        }

        if (event.artists && event.artists.length > 0) {
            selectedArtists = event.artists.map(a => ({ id: a.id, name: a.name }));
            renderArtistTags();
        }

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

        updateSummaries();

    } catch (error) {
        orgNotify('Eroare la încărcarea activității: ' + (error.message || 'Încearcă din nou.'), 'error');
        window.location.href = '/organizator/events';
    }
}

function hideCreateForm() {
    document.getElementById('create-event-view').classList.add('hidden');
    document.getElementById('events-view').classList.remove('hidden');

    const titleEl = document.querySelector('#create-event-view h1');
    if (titleEl) titleEl.textContent = 'Activitate nouă';

    document.getElementById('saved-event-id').value = '';

    history.pushState({}, '', '/organizator/events');
    loadEvents();
}

// ==================== CATEGORIES & GENRES ====================

async function loadCategories() {
    try {
        const response = await BileteOnlineAPI.organizer.getEventCategories();
        const categories = response.data?.categories || response.categories || [];
        categoriesData = categories;
        const select = document.getElementById('category-select');
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
        const response = await BileteOnlineAPI.organizer.getEventGenres(typeIds);
        const genres = response.data?.genres || response.genres || [];

        if (genres.length === 0) {
            genresContainer.classList.add('hidden');
            availableGenres = [];
            return;
        }

        availableGenres = genres;
        genresContainer.classList.remove('hidden');
        selectedGenres = selectedGenres.filter(sg => genres.some(g => g.id === sg.id));
        renderGenreTags();
    } catch (e) {
        console.error('Failed to load genres:', e);
        genresContainer.classList.add('hidden');
    }
}

// ==================== GENRE MULTISELECT ====================

function normalizeSearchTerm(str) {
    return (str || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

function initGenreSearch() {
    const input = document.getElementById('genres-search-input');
    const dropdown = document.getElementById('genres-dropdown');

    input.addEventListener('input', function() {
        const query = normalizeSearchTerm(this.value.trim());
        const filtered = availableGenres.filter(g =>
            !selectedGenres.some(sg => sg.id === g.id) &&
            (query === '' || normalizeSearchTerm(g.name).includes(query))
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
                const response = await BileteOnlineAPI.organizer.searchArtists(query);
                const artists = response.data?.artists || response.artists || [];
                const filtered = artists.filter(a => !selectedArtists.some(sa => sa.id === a.id));

                let html = filtered.map(a =>
                    `<div class="multiselect-option" onclick="selectArtist(${a.id}, '${a.name.replace(/'/g, "\\'")}')">${a.name}</div>`
                ).join('');

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
        const response = await BileteOnlineAPI.organizer.createArtist(name);
        const artist = response.data?.artist || response.artist;
        if (artist) {
            selectArtist(artist.id, artist.name);
        }
    } catch (e) {
        console.error('Failed to create artist:', e);
        orgNotify('Nu s-a putut crea artistul', 'error');
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
            const response = await BileteOnlineAPI.organizer.searchVenues(query);
            const venues = response.data?.venues || response.venues || [];

            if (venues.length === 0) {
                dropdown.innerHTML = '<div class="venue-option" style="cursor:default;color:#9ca3af;">Niciun rezultat găsit</div>';
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = venues.map(venue => `
                <div class="venue-option ${venue.is_marketplace ? 'bg-amber-50' : ''}" onclick="selectVenue(${JSON.stringify(venue).replace(/"/g, '&quot;')})">
                    <div class="flex items-center gap-2">
                        <div class="text-sm font-bold">${venue.name}</div>
                        ${venue.is_marketplace ? '<span class="rounded bg-ochre/20 px-1.5 py-0.5 text-[10px] font-bold text-ochre">Partener bilete.online</span>' : ''}
                    </div>
                    <div class="text-xs text-ink-soft">${[venue.city, venue.address].filter(Boolean).join(' - ')}</div>
                </div>
            `).join('');
            dropdown.classList.remove('hidden');
        } catch (e) {
            console.error('Venue search failed:', e);
            dropdown.innerHTML = '<div class="venue-option" style="cursor:default;color:#E84527;">Eroare la căutare</div>';
            dropdown.classList.remove('hidden');
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(venueSearchTimeout);
        const query = this.value.trim();
        document.getElementById('selected-venue-id').value = '';
        const notice = document.getElementById('venue-suggestion-notice');
        if (notice && query.length >= 2) {
            notice.classList.remove('hidden');
        } else if (notice) {
            notice.classList.add('hidden');
        }

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
    const notice = document.getElementById('venue-suggestion-notice');
    if (notice) notice.classList.add('hidden');
    updateSummaries();
}

// ==================== WYSIWYG EDITORS ====================

function initEditors() {
    if (descriptionEditor) return;

    const baseConfig = {
        base_url: 'https://cdn.jsdelivr.net/npm/tinymce@6.8.5',
        suffix: '.min',
        menubar: false,
        statusbar: false,
        plugins: 'lists link autolink',
        toolbar: 'bold italic underline | bullist numlist | link | hr | undo redo | removeformat',
        content_style: 'body { font-family: Hanken Grotesk, sans-serif; font-size: 14px; }',
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
        placeholder: 'Scrie descrierea activității aici...',
        plugins: 'lists link autolink media',
        toolbar: 'bold italic underline | bullist numlist | link media | hr | undo redo | removeformat',
        extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen|loading|referrerpolicy|title]',
        valid_children: '+body[iframe],+div[iframe]',
        media_live_embeds: true,
        media_alt_source: false,
        media_poster: false,
        setup: function(editor) {
            editor.on('Change KeyUp', function() { updateSummaries(); });
            editor.on('init', function() {
                descriptionEditor = editor;
                if (pendingDescription !== null) {
                    editor.setContent(pendingDescription);
                    pendingDescription = null;
                    if (typeof updateSummaries === 'function') updateSummaries();
                }
            });
        }
    });

    tinymce.init({
        ...baseConfig,
        selector: '#ticket-terms-editor',
        height: 200,
        placeholder: 'Conditii de participare, restrictii, politica de retur...',
        setup: function(editor) {
            editor.on('init', function() {
                ticketTermsEditor = editor;
                if (pendingTicketTerms !== null) {
                    editor.setContent(pendingTicketTerms);
                    pendingTicketTerms = null;
                }
            });
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
            counter.parentElement.classList.add('text-vermilion');
            counter.parentElement.classList.remove('text-ink-soft');
        } else {
            counter.parentElement.classList.remove('text-vermilion');
            counter.parentElement.classList.add('text-ink-soft');
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

    const name = form.querySelector('[name="name"]').value;
    const catSelect = form.querySelector('[name="marketplace_event_category_id"]');
    const catText = catSelect.selectedOptions[0]?.text || '';
    if (name || (catText && catText !== 'Selecteaza categoria')) {
        document.getElementById('summary-1').textContent = [name, catText !== 'Selecteaza categoria' ? catText : ''].filter(Boolean).join(' • ');
    }

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

    const venueName = form.querySelector('[name="venue_name"]').value;
    const venueCity = form.querySelector('[name="venue_city"]').value;
    if (venueName || venueCity) {
        document.getElementById('summary-3').textContent = [venueName, venueCity].filter(Boolean).join(', ');
    }

    const desc = descriptionEditor ? descriptionEditor.getContent() : form.querySelector('[name="description"]').value;
    if (desc) {
        const plainText = desc.replace(/<[^>]*>/g, '').trim();
        document.getElementById('summary-4').textContent = plainText.substring(0, 60) + (plainText.length > 60 ? '...' : '');
    }

    const posterInput = form.querySelector('[name="poster"]');
    const coverInput = form.querySelector('[name="cover_image"]');
    const posterPreviewEl = document.getElementById('poster-preview');
    const coverPreviewEl = document.getElementById('cover-preview');
    const mediaItems = [];
    if ((posterInput && posterInput.files.length > 0) || (posterPreviewEl && !posterPreviewEl.classList.contains('hidden'))) mediaItems.push('Poster');
    if ((coverInput && coverInput.files.length > 0) || (coverPreviewEl && !coverPreviewEl.classList.contains('hidden'))) mediaItems.push('Cover');
    document.getElementById('summary-5').textContent = mediaItems.length > 0 ? mediaItems.join(', ') + ' adăugate' : '';

    const ticketItems = document.querySelectorAll('.ticket-type-item');
    const ticketSummary = [];
    ticketItems.forEach((item, i) => {
        const tName = item.querySelector(`[name="ticket_name_${i}"]`)?.value;
        const tPrice = item.querySelector(`[name="ticket_price_${i}"]`)?.value;
        if (tName && tPrice) ticketSummary.push(`${tName}: ${tPrice} RON`);
    });
    document.getElementById('summary-6').textContent = ticketSummary.join(' | ');

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
    const posterPreview = document.getElementById('poster-preview');
    const coverPreview = document.getElementById('cover-preview');
    const hasPoster = (posterInput?.files?.length > 0) || (posterPreview && !posterPreview.classList.contains('hidden'));
    const hasCover = (coverInput?.files?.length > 0) || (coverPreview && !coverPreview.classList.contains('hidden'));
    const step5Done = hasPoster && hasCover;
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
        indicator.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
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
        <div class="ticket-type-item rounded-xl border-2 border-ink/15 p-4" data-index="${index}">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-bold">Tip bilet #${index + 1}</h4>
                <button type="button" onclick="removeTicketType(this)" class="remove-ticket-btn text-vermilion transition hover:text-vermilion-d">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Nume bilet <span class="text-vermilion">*</span></label>
                    <input type="text" name="ticket_name_${index}" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Standard, VIP, Early Bird">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Preț (RON) <span class="text-vermilion">*</span></label>
                    <input type="number" name="ticket_price_${index}" required step="0.01" min="0" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="0.00">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Stoc bilete</label>
                    <input type="number" name="ticket_quantity_${index}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Nelimitat">
                </div>
            </div>
            <div class="mt-3">
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Descriere bilet</label>
                <input type="text" name="ticket_desc_${index}" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Acces general, loc nenumerotat">
            </div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Min. bilete/comandă</label>
                    <input type="number" name="ticket_min_${index}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="1">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Max. bilete/comandă</label>
                    <input type="number" name="ticket_max_${index}" min="1" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="10">
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
            zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.add('border-vermilion', 'bg-vermilion/10'); });
        });
        ['dragleave', 'drop'].forEach(evt => {
            zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('border-vermilion', 'bg-vermilion/10'); });
        });
        zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length === 0) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) { orgNotify('Doar fișiere imagine sunt acceptate', 'error'); return; }
            if (file.size > 5 * 1024 * 1024) { orgNotify('Fișierul depășește 5MB', 'error'); return; }
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

async function uploadEventImagesIfNeeded(eventId) {
    const form = document.getElementById('create-event-form');
    const posterInput = form.querySelector('[name="poster"]');
    const coverInput = form.querySelector('[name="cover_image"]');

    const posterFile = posterInput?.files?.[0] || null;
    const coverFile = coverInput?.files?.[0] || null;

    if (!posterFile && !coverFile) return;

    try {
        await BileteOnlineAPI.organizer.uploadEventImages(eventId, posterFile, coverFile);
    } catch (error) {
        console.error('Image upload error:', error);
        orgNotify('Activitatea a fost salvată, dar imaginile nu s-au putut încărca. Încearcă din nou.', 'error');
    }
}

function collectFormData() {
    const form = document.getElementById('create-event-form');
    const durationMode = form.querySelector('[name="duration_mode"]:checked')?.value;

    const startDate = form.querySelector('[name="start_date"]').value;
    const startTime = form.querySelector('[name="start_time"]').value;
    let startsAt = null;
    if (startDate && startTime) {
        startsAt = `${startDate}T${startTime}:00`;
    } else if (startDate) {
        startsAt = `${startDate}T00:00:00`;
    }

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

    let doorsOpenAt = null;
    if (durationMode === 'single_day') {
        const doorTime = form.querySelector('[name="door_time"]').value;
        if (doorTime && startDate) doorsOpenAt = `${startDate}T${doorTime}:00`;
    } else if (durationMode === 'range') {
        const doorTimeRange = form.querySelector('[name="door_time_range"]').value;
        if (doorTimeRange && startDate) doorsOpenAt = `${startDate}T${doorTimeRange}:00`;
    }

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

    const tagsStr = form.querySelector('[name="tags"]').value;
    const tags = tagsStr ? tagsStr.split(',').map(t => t.trim()).filter(Boolean) : null;

    const data = {
        name: form.querySelector('[name="name"]').value,
        starts_at: startsAt,
        duration_mode: durationMode || 'single_day',
        venue_name: form.querySelector('[name="venue_name"]').value,
        venue_city: form.querySelector('[name="venue_city"]').value,
    };

    const categoryId = form.querySelector('[name="marketplace_event_category_id"]').value;
    if (categoryId) data.marketplace_event_category_id = parseInt(categoryId);

    const venueId = document.getElementById('selected-venue-id').value;
    if (venueId) data.venue_id = parseInt(venueId);

    const genreIds = getSelectedGenreIds();
    if (genreIds.length > 0) data.genre_ids = genreIds;

    const artistIds = selectedArtists.map(a => a.id);
    if (artistIds.length > 0) data.artist_ids = artistIds;

    const shortDesc = form.querySelector('[name="short_description"]').value;
    if (shortDesc) data.short_description = shortDesc;

    const description = descriptionEditor ? descriptionEditor.getContent() : '';
    if (description && description !== '<p><br></p>' && description.trim() !== '') data.description = description;

    const ticketTerms = ticketTermsEditor ? ticketTermsEditor.getContent() : '';
    if (ticketTerms && ticketTerms !== '<p><br></p>' && ticketTerms.trim() !== '') data.ticket_terms = ticketTerms;

    if (tags && tags.length > 0) data.tags = tags;
    if (endsAt) data.ends_at = endsAt;
    if (doorsOpenAt) data.doors_open_at = doorsOpenAt;

    const venueAddress = form.querySelector('[name="venue_address"]').value;
    if (venueAddress) data.venue_address = venueAddress;

    const websiteUrl = form.querySelector('[name="website_url"]').value;
    if (websiteUrl) data.event_website_url = websiteUrl;

    const facebookUrl = form.querySelector('[name="facebook_url"]').value;
    if (facebookUrl) data.facebook_url = facebookUrl;

    const videoInput = form.querySelector('[name="video_url"]');
    if (videoInput) data.video_url = videoInput.value.trim();

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
        orgNotify('Numele activității este obligatoriu.', 'error');
        toggleAccordion(1);
        return;
    }

    if (data.short_description) {
        const wordCount = data.short_description.trim().split(/\s+/).filter(w => w.length > 0).length;
        if (wordCount > 120) {
            orgNotify('Descrierea scurtă nu poate depăși 120 de cuvinte.', 'error');
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
            result = await BileteOnlineAPI.organizer.updateEvent(savedEventId, data);
        } else {
            result = await BileteOnlineAPI.organizer.createEvent(data);
        }

        if (result.success !== false) {
            const eventId = result.data?.event?.id || result.data?.id || savedEventId;
            if (eventId) {
                document.getElementById('saved-event-id').value = eventId;
                await uploadEventImagesIfNeeded(eventId);
            }

            saveStatus.textContent = 'Salvat la ' + new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
            saveStatus.classList.remove('hidden');
            orgNotify('Activitatea a fost salvată ca ciornă!', 'success');
        } else {
            const msg = result.message || 'Eroare la salvare.';
            orgNotify(msg, 'error');
        }
    } catch (error) {
        const errorMsg = error.message || 'A apărut o eroare. Încearcă din nou.';
        orgNotify(errorMsg, 'error');
    }

    btnText.classList.remove('hidden');
    btnSpinner.classList.add('hidden');
}

async function saveAndSubmitEvent() {
    const data = collectFormData();

    if (!data.name) {
        orgNotify('Numele activității este obligatoriu.', 'error');
        toggleAccordion(1);
        return;
    }
    if (!data.starts_at) {
        orgNotify('Data și ora activității sunt obligatorii.', 'error');
        toggleAccordion(2);
        return;
    }
    if (!data.venue_name || !data.venue_city) {
        orgNotify('Numele locației și orașul sunt obligatorii.', 'error');
        toggleAccordion(3);
        return;
    }
    if (!data.ticket_types || data.ticket_types.length === 0) {
        orgNotify('Adaugă cel puțin un tip de bilet pentru a trimite spre aprobare.', 'error');
        toggleAccordion(6);
        return;
    }

    try {
        const savedEventId = document.getElementById('saved-event-id').value;
        let eventId = savedEventId;

        if (savedEventId) {
            await BileteOnlineAPI.organizer.updateEvent(savedEventId, data);
        } else {
            const result = await BileteOnlineAPI.organizer.createEvent(data);
            if (result.success === false) {
                orgNotify(result.message || 'Eroare la creare.', 'error');
                return;
            }
            eventId = result.data?.event?.id || result.data?.id;
            if (eventId) {
                document.getElementById('saved-event-id').value = eventId;
            }
        }

        if (eventId) {
            await uploadEventImagesIfNeeded(eventId);
        }

        if (eventId) {
            const submitResult = await BileteOnlineAPI.organizer.submitEvent(eventId);
            if (submitResult.success !== false) {
                orgNotify('Activitatea a fost trimisă spre aprobare!', 'success');
                setTimeout(() => hideCreateForm(), 1500);
            } else {
                orgNotify(submitResult.message || 'Eroare la trimiterea spre aprobare.', 'error');
            }
        } else {
            orgNotify('Nu s-a putut identifica activitatea creată.', 'error');
        }
    } catch (error) {
        orgNotify(error.message || 'A apărut o eroare. Încearcă din nou.', 'error');
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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#create-event-form input, #create-event-form select, #create-event-form textarea').forEach(el => {
        el.addEventListener('change', updateSummaries);
        el.addEventListener('input', localDebounce(updateSummaries, 300));
    });
});

function localDebounce(fn, ms) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}

// ============ EVENT STATUS ACTIONS ============

let currentEventStatus = {
    is_sold_out: false,
    door_sales_only: false,
    is_postponed: false,
    is_cancelled: false
};

const STATUS_INDICATOR_CLASSES = {
    sold_out:    'rounded-full bg-sky/15 px-2.5 py-1 text-xs font-bold text-sky',
    door_sales:  'rounded-full bg-ochre/15 px-2.5 py-1 text-xs font-bold text-ochre',
    postponed:   'rounded-full bg-ochre/15 px-2.5 py-1 text-xs font-bold text-ochre',
    cancelled:   'rounded-full bg-vermilion/15 px-2.5 py-1 text-xs font-bold text-vermilion'
};

function updateStatusIndicators() {
    const container = document.getElementById('status-indicators');
    if (!container) return;

    let html = '';
    if (currentEventStatus.is_sold_out) {
        html += `<span class="${STATUS_INDICATOR_CLASSES.sold_out}">Sold Out</span>`;
    }
    if (currentEventStatus.door_sales_only) {
        html += `<span class="${STATUS_INDICATOR_CLASSES.door_sales}">Door Sales Only</span>`;
    }
    if (currentEventStatus.is_postponed) {
        html += `<span class="${STATUS_INDICATOR_CLASSES.postponed}">Amânat</span>`;
    }
    if (currentEventStatus.is_cancelled) {
        html += `<span class="${STATUS_INDICATOR_CLASSES.cancelled}">Anulat</span>`;
    }
    container.innerHTML = html;

    // Toggle button states (fixed class strings, no dynamic Tailwind)
    const onCls = ['bg-forest', 'text-paper', 'border-forest'];
    const secCls = ['border-ink'];
    const ochreCls = ['bg-ochre', 'text-paper'];
    const vermCls = ['bg-vermilion', 'text-paper'];

    const btnSoldOut = document.getElementById('btn-sold-out');
    const btnDoorSales = document.getElementById('btn-door-sales');
    const btnPostponed = document.getElementById('btn-postponed');
    const btnCancelled = document.getElementById('btn-cancelled');

    if (btnSoldOut) {
        btnSoldOut.classList.remove('bg-forest', 'text-paper', 'border-forest', 'border-ink');
        btnSoldOut.classList.add(...(currentEventStatus.is_sold_out ? onCls : secCls));
        btnSoldOut.querySelector('span').textContent = currentEventStatus.is_sold_out ? 'Anulează Sold Out' : 'Sold Out';
    }
    if (btnDoorSales) {
        btnDoorSales.classList.remove('bg-forest', 'text-paper', 'border-forest', 'border-ink');
        btnDoorSales.classList.add(...(currentEventStatus.door_sales_only ? onCls : secCls));
        btnDoorSales.querySelector('span').textContent = currentEventStatus.door_sales_only ? 'Anulează Door Sales' : 'Door Sales Only';
    }
    if (btnPostponed) {
        btnPostponed.classList.remove('bg-forest', 'text-paper', 'border-forest', 'bg-ochre');
        btnPostponed.classList.add(...(currentEventStatus.is_postponed ? onCls : ochreCls));
        btnPostponed.querySelector('span').textContent = currentEventStatus.is_postponed ? 'Anulează Amânare' : 'Amânat';
    }
    if (btnCancelled) {
        btnCancelled.classList.remove('bg-forest', 'text-paper', 'border-forest', 'bg-vermilion');
        btnCancelled.classList.add(...(currentEventStatus.is_cancelled ? onCls : vermCls));
        btnCancelled.querySelector('span').textContent = currentEventStatus.is_cancelled ? 'Anulează Anulare' : 'Anulat';
    }
}

async function toggleSoldOut() {
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    const newValue = !currentEventStatus.is_sold_out;

    try {
        orgNotify(newValue ? 'Se marchează ca Sold Out...' : 'Se anulează Sold Out...', 'info');
        const response = await BileteOnlineAPI.patch(`/organizer/events/${eventId}/status`, { is_sold_out: newValue });
        if (response.success) {
            currentEventStatus.is_sold_out = newValue;
            updateStatusIndicators();
            orgNotify(newValue ? 'Activitatea a fost marcată ca Sold Out' : 'Sold Out a fost anulat', 'success');
        } else {
            orgNotify(response.message || 'Eroare la actualizare', 'error');
        }
    } catch (error) {
        orgNotify('Eroare la actualizare', 'error');
    }
}

async function toggleDoorSales() {
    const eventId = document.getElementById('saved-event-id').value;
    if (!eventId) return;

    const newValue = !currentEventStatus.door_sales_only;

    try {
        orgNotify(newValue ? 'Se activează Door Sales Only...' : 'Se dezactivează Door Sales Only...', 'info');
        const response = await BileteOnlineAPI.patch(`/organizer/events/${eventId}/status`, { door_sales_only: newValue });
        if (response.success) {
            currentEventStatus.door_sales_only = newValue;
            updateStatusIndicators();
            orgNotify(newValue ? 'Door Sales Only a fost activat' : 'Door Sales Only a fost dezactivat', 'success');
        } else {
            orgNotify(response.message || 'Eroare la actualizare', 'error');
        }
    } catch (error) {
        orgNotify('Eroare la actualizare', 'error');
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
        orgNotify('Se salvează amânarea...', 'info');
        const response = await BileteOnlineAPI.patch(`/organizer/events/${eventId}/status`, data);
        if (response.success) {
            currentEventStatus.is_postponed = true;
            updateStatusIndicators();
            closePostponedModal();
            orgNotify('Activitatea a fost marcată ca amânată', 'success');
        } else {
            orgNotify(response.message || 'Eroare la salvarea amânării', 'error');
        }
    } catch (error) {
        orgNotify('Eroare la salvarea amânării', 'error');
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

    if (!confirm('Ești sigur că vrei să anulezi activitatea? Această acțiune nu poate fi anulată și toate comenzile vor fi rambursate.')) {
        return;
    }

    const form = e.target;
    const data = {
        reason: form.cancel_reason.value
    };

    try {
        orgNotify('Se anulează activitatea...', 'info');
        const response = await BileteOnlineAPI.post(`/organizer/events/${eventId}/cancel`, data);
        if (response.success) {
            currentEventStatus.is_cancelled = true;
            updateStatusIndicators();
            closeCancelledModal();
            orgNotify('Activitatea a fost anulată. ' + (response.data?.orders_refunded > 0 ? `${response.data.orders_refunded} comenzi au fost rambursate.` : ''), 'success');
        } else {
            orgNotify(response.message || 'Eroare la anularea activității', 'error');
        }
    } catch (error) {
        orgNotify('Eroare la anularea activității', 'error');
    }
}

// ==================== OFFLINE DETECTION & AUTO-SAVE ====================

let _isOffline = !navigator.onLine;
let _pendingSave = false;
let _offlineBanner = null;

function createOfflineBanner() {
    if (_offlineBanner) return _offlineBanner;

    _offlineBanner = document.createElement('div');
    _offlineBanner.id = 'offline-banner';
    _offlineBanner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;padding:12px 20px;text-align:center;font-weight:700;font-size:14px;transition:transform 0.3s ease,opacity 0.3s ease;transform:translateY(-100%);opacity:0;display:flex;align-items:center;justify-content:center;gap:8px;';
    document.body.appendChild(_offlineBanner);
    return _offlineBanner;
}

function showOfflineBanner(isOffline) {
    const banner = createOfflineBanner();

    if (isOffline) {
        banner.style.background = '#E84527';
        banner.style.color = '#F5EFE6';
        banner.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M5.636 18.364a9 9 0 010-12.728M12 9v4m0 4h.01"/></svg>' +
            '<span>Conexiune la internet pierdută. Poți continua să editezi — salvarea se va face automat când revine conexiunea.</span>';
    } else {
        banner.style.background = '#1E4A3D';
        banner.style.color = '#F5EFE6';
        banner.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
            '<span>Conexiune restabilită!' + (_pendingSave ? ' Se salvează automat...' : '') + '</span>';
    }

    requestAnimationFrame(() => {
        banner.style.transform = 'translateY(0)';
        banner.style.opacity = '1';
    });

    if (!isOffline) {
        setTimeout(() => {
            banner.style.transform = 'translateY(-100%)';
            banner.style.opacity = '0';
        }, 4000);
    }
}

window.addEventListener('offline', () => {
    _isOffline = true;
    _pendingSave = true;
    showOfflineBanner(true);
});

window.addEventListener('online', () => {
    _isOffline = false;
    showOfflineBanner(false);

    if (_pendingSave) {
        const createView = document.getElementById('create-event-view');
        const eventName = document.querySelector('#create-event-form [name="name"]');
        if (createView && !createView.classList.contains('hidden') && eventName && eventName.value.trim()) {
            setTimeout(async () => {
                try {
                    await saveEventDraft();
                    _pendingSave = false;
                } catch (e) {
                    orgNotify('Salvarea automată a eșuat. Te rugăm salvează manual.', 'error');
                }
            }, 1000);
        } else {
            _pendingSave = false;
        }
    }
});

const _originalSaveEventDraft = saveEventDraft;
saveEventDraft = async function() {
    if (_isOffline) {
        _pendingSave = true;
        orgNotify('Nu există conexiune la internet. Modificările vor fi salvate automat când revine conexiunea.', 'error');
        return;
    }
    return _originalSaveEventDraft();
};

const _originalSaveAndSubmitEvent = saveAndSubmitEvent;
saveAndSubmitEvent = async function() {
    if (_isOffline) {
        orgNotify('Nu poți trimite spre aprobare fără conexiune la internet. Așteaptă restabilirea conexiunii.', 'error');
        return;
    }
    return _originalSaveAndSubmitEvent();
};

// =============================================================
// EDIT REDESIGN: sticky header shadow / scroll-spy / live preview / hydrate
// =============================================================
(function () {
    let scrollSpyObserver = null;
    let outlineEls = null;

    function initStickyHeaderShadow() {
        const header = document.getElementById('event-edit-header');
        if (!header) return;
        const onScroll = () => {
            if (window.scrollY > 8) header.classList.add('is-scrolled');
            else header.classList.remove('is-scrolled');
        };
        document.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function initScrollSpy() {
        if (scrollSpyObserver) scrollSpyObserver.disconnect();
        outlineEls = document.querySelectorAll('#edit-outline [data-outline]');
        if (!outlineEls.length) return;
        const sections = [];
        for (let i = 1; i <= 7; i++) {
            const sec = document.getElementById('step-' + i);
            if (sec) sections.push(sec);
        }
        if (!sections.length) return;

        scrollSpyObserver = new IntersectionObserver((entries) => {
            const visible = entries
                .filter(e => e.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
            if (!visible.length) return;
            const step = visible[0].target.getAttribute('data-step');
            outlineEls.forEach(el => {
                if (el.getAttribute('data-outline') === step) el.classList.add('is-active');
                else el.classList.remove('is-active');
            });
            sections.forEach(sec => {
                if (sec.getAttribute('data-step') === step) sec.classList.add('is-active-section');
                else sec.classList.remove('is-active-section');
            });
        }, { rootMargin: '-200px 0px -50% 0px', threshold: 0 });

        sections.forEach(sec => scrollSpyObserver.observe(sec));

        outlineEls.forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const step = el.getAttribute('data-outline');
                const sec = document.getElementById('step-' + step);
                if (!sec) return;
                if (window.innerWidth < 1024) {
                    const isOpen = sec.getAttribute('data-open') === 'true';
                    if (!isOpen && typeof toggleAccordion === 'function') {
                        toggleAccordion(parseInt(step, 10));
                    }
                }
                sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function syncOutlineStatuses() {
        const required = { 1: 'name', 2: ['start_date','start_time'], 3: ['venue_name','venue_city'] };
        const form = document.getElementById('create-event-form');
        if (!form) return;
        const isFilled = (n) => {
            const f = form.querySelector('[name="' + n + '"]');
            return f && (f.value || '').trim() !== '';
        };
        document.querySelectorAll('#edit-outline [data-outline]').forEach(item => {
            const step = parseInt(item.getAttribute('data-outline'), 10);
            const indicator = document.querySelector('.accordion-section[data-step="' + step + '"] .step-indicator');
            const status = item.querySelector('.outline-status');
            if (!status) return;
            status.className = 'outline-status';
            if (indicator && indicator.classList.contains('completed')) {
                status.classList.add('complete');
            } else {
                const req = required[step];
                let missing = false;
                if (req) {
                    const fields = Array.isArray(req) ? req : [req];
                    missing = fields.some(f => !isFilled(f));
                }
                if (missing) status.classList.add('required');
                else status.classList.add('partial');
            }
        });

        const issuesNode = document.getElementById('outline-issues');
        const countNode = document.getElementById('outline-issues-count');
        if (issuesNode && countNode) {
            const requiredCount = document.querySelectorAll('#edit-outline .outline-status.required').length;
            if (requiredCount > 0) {
                issuesNode.classList.remove('hidden');
                countNode.textContent = requiredCount;
            } else {
                issuesNode.classList.add('hidden');
            }
        }
    }

    function hookOutlineSync() {
        if (typeof window.updateStepIndicators !== 'function') return;
        if (window.__outlineSyncWrapped) return;
        const original = window.updateStepIndicators;
        window.updateStepIndicators = function () {
            const r = original.apply(this, arguments);
            try { syncOutlineStatuses(); } catch (e) {}
            return r;
        };
        window.__outlineSyncWrapped = true;
    }

    // ---- Live preview drawer ----
    window.openLivePreview = function () {
        renderLivePreview();
        const drawer = document.getElementById('live-preview-drawer');
        const backdrop = document.getElementById('live-preview-backdrop');
        if (!drawer || !backdrop) return;
        backdrop.classList.remove('hidden');
        drawer.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            backdrop.classList.add('is-open');
            drawer.classList.add('is-open');
        });
    };
    window.closeLivePreview = function () {
        const drawer = document.getElementById('live-preview-drawer');
        const backdrop = document.getElementById('live-preview-backdrop');
        if (!drawer || !backdrop) return;
        backdrop.classList.remove('is-open');
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        setTimeout(() => {
            backdrop.classList.add('hidden');
            drawer.classList.add('hidden');
        }, 250);
    };

    function renderLivePreview() {
        const body = document.getElementById('live-preview-body');
        if (!body) return;
        const form = document.getElementById('create-event-form');
        const f = (n) => form.querySelector('[name="' + n + '"]')?.value || '';

        const name = f('name') || 'Activitate fără nume';
        const shortDesc = f('short_description');
        const date = f('start_date');
        const time = f('start_time');
        const venueName = f('venue_name');
        const venueCity = f('venue_city');
        const cat = form.querySelector('[name="marketplace_event_category_id"]');
        const catText = cat?.selectedOptions[0]?.text;

        const posterImg = document.getElementById('poster-img');
        const coverImg = document.getElementById('cover-img');
        const posterVisible = document.getElementById('poster-preview') && !document.getElementById('poster-preview').classList.contains('hidden');
        const coverVisible = document.getElementById('cover-preview') && !document.getElementById('cover-preview').classList.contains('hidden');
        const heroSrc = (coverVisible && coverImg && coverImg.getAttribute('src')) || (posterVisible && posterImg && posterImg.getAttribute('src')) || '';

        const ticketRows = [];
        document.querySelectorAll('.ticket-type-item').forEach((item, i) => {
            const tn = item.querySelector(`[name="ticket_name_${i}"]`)?.value;
            const tp = item.querySelector(`[name="ticket_price_${i}"]`)?.value;
            if (tn) ticketRows.push({ name: tn, price: tp ? `${tp} RON` : '—' });
        });

        const dateStr = date ? new Date(date).toLocaleDateString('ro-RO', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }) : '';
        const esc = (s) => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

        const heroHtml = heroSrc
            ? `<img src="${esc(heroSrc)}" alt="" class="h-44 w-full rounded-xl border-2 border-ink/15 object-cover" />`
            : `<div class="flex h-44 w-full items-center justify-center rounded-xl border-2 border-ink/15 bg-paper-2 text-xs font-bold text-ink-soft">FĂRĂ IMAGINE</div>`;

        const catBadge = catText && catText !== 'Selecteaza categoria'
            ? `<span class="inline-block rounded-md bg-paper-2 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-ink-soft">${esc(catText)}</span>`
            : '';

        const ticketsHtml = ticketRows.length
            ? `<div class="space-y-1.5">${ticketRows.map(t => `
                <div class="flex items-center justify-between rounded-lg border-2 border-ink/10 bg-paper-2 px-3 py-2">
                    <span class="text-sm">${esc(t.name)}</span>
                    <span class="text-sm font-bold text-vermilion">${esc(t.price)}</span>
                </div>`).join('')}</div>`
            : `<p class="text-xs text-ink-soft">Fără tipuri de bilet definite încă.</p>`;

        body.innerHTML = `
            ${heroHtml}
            <div class="space-y-1">
                ${catBadge}
                <h2 class="font-display text-xl font-bold leading-tight">${esc(name)}</h2>
                ${dateStr ? `<p class="flex items-center gap-1.5 text-sm text-ink-soft">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    ${esc(dateStr)}${time ? ', ora ' + esc(time) : ''}
                </p>` : ''}
                ${(venueName || venueCity) ? `<p class="flex items-center gap-1.5 text-sm text-ink-soft">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    ${esc([venueName, venueCity].filter(Boolean).join(', '))}
                </p>` : ''}
            </div>
            ${shortDesc ? `<p class="border-t-2 border-ink/10 pt-2 text-sm leading-relaxed">${esc(shortDesc)}</p>` : ''}
            <div class="border-t-2 border-ink/10 pt-2">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-soft">Bilete disponibile</h4>
                ${ticketsHtml}
            </div>
        `;

        const eventId = document.getElementById('saved-event-id')?.value;
        const publicLink = document.getElementById('live-preview-public-link');
        if (publicLink && eventId && window.__currentEventSlug) {
            publicLink.href = '/bilete/' + window.__currentEventSlug;
            publicLink.classList.remove('hidden');
        } else if (publicLink) {
            publicLink.classList.add('hidden');
        }
    }

    // ---- Hydrate sticky header ----
    window.hydrateEventHeader = function (event) {
        if (!event) return;
        try {
            const idChip = document.getElementById('header-event-id-chip');
            if (idChip && event.id) { idChip.textContent = '#' + event.id; idChip.classList.remove('hidden'); }

            const dateEl = document.getElementById('header-event-date');
            if (dateEl) {
                const dateRaw = event.starts_at || event.event_date || event.range_start_date;
                const dateSpan = dateEl.querySelector('span');
                if (dateRaw && dateSpan) {
                    const d = new Date(dateRaw);
                    dateSpan.textContent = d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }) +
                        (event.start_time ? ', ' + event.start_time : '');
                    dateEl.classList.remove('hidden');
                }
            }

            const venueEl = document.getElementById('header-event-venue');
            if (venueEl) {
                const venueSpan = venueEl.querySelector('span');
                const parts = [event.venue_name || (event.venue && event.venue.name), event.venue_city || event.city].filter(Boolean);
                if (parts.length && venueSpan) {
                    venueSpan.textContent = parts.join(', ');
                    venueEl.classList.remove('hidden');
                }
            }

            const statusEl = document.getElementById('header-event-status');
            if (statusEl) {
                const statusMap = {
                    published: { c: 'status-published', l: 'Publicat' },
                    active:    { c: 'status-published', l: 'Activ' },
                    draft:     { c: 'status-draft',     l: 'Ciornă' },
                    pending_review: { c: 'status-pending', l: 'În revizuire' },
                    rejected:  { c: 'status-rejected',  l: 'Respins' },
                    cancelled: { c: 'status-cancelled', l: 'Anulat' },
                    postponed: { c: 'status-postponed', l: 'Amânat' },
                    sold_out:  { c: 'status-soldout',   l: 'Sold Out' },
                    ended:     { c: 'status-ended',     l: 'Încheiat' },
                };
                const m = statusMap[event.status] || statusMap.draft;
                statusEl.classList.remove('status-published','status-draft','status-pending','status-rejected','status-cancelled','status-postponed','status-soldout','status-ended');
                statusEl.classList.add(m.c);
                const lbl = statusEl.querySelector('.status-label');
                if (lbl) lbl.textContent = m.l;
                statusEl.classList.remove('hidden');
            }

            document.getElementById('header-preview-btn')?.classList.remove('hidden');
            document.getElementById('header-submit-btn')?.classList.remove('hidden');
            document.getElementById('mobile-submit-btn')?.classList.remove('hidden');
            window.__currentEventSlug = event.slug || null;
        } catch (e) { console.warn('hydrateEventHeader failed', e); }
    };

    window.showEditSkeleton = function () {
        const form = document.getElementById('create-event-form');
        if (!form) return;
        const cards = Array.from(form.querySelectorAll('.accordion-section'));
        if (!cards.length) return;
        cards.forEach((c, i) => {
            const body = c.querySelector('.accordion-body');
            if (!body) return;
            if (body.querySelector('.edit-skeleton')) return;
            const overlay = document.createElement('div');
            overlay.className = 'edit-skeleton-wrap absolute inset-0 px-5 pb-5 bg-paper/70 backdrop-blur-[1px] flex flex-col gap-2 pt-2 pointer-events-none';
            overlay.innerHTML = `
                <div class="w-1/3 h-3 edit-skeleton"></div>
                <div class="w-full mt-1 edit-skeleton h-9"></div>
                ${i % 2 === 0 ? '<div class="w-2/3 mt-1 edit-skeleton h-9"></div>' : ''}
            `;
            c.style.position = 'relative';
            c.appendChild(overlay);
        });
    };
    window.hideEditSkeleton = function () {
        document.querySelectorAll('.edit-skeleton-wrap').forEach(el => el.remove());
    };

    document.addEventListener('DOMContentLoaded', function () {
        initStickyHeaderShadow();

        const view = document.getElementById('create-event-view');
        if (!view) return;
        const tryInit = () => {
            if (view.classList.contains('hidden')) return;
            initScrollSpy();
            hookOutlineSync();
            try { syncOutlineStatuses(); } catch (e) {}
        };
        new MutationObserver(tryInit).observe(view, { attributes: true, attributeFilter: ['class'] });
        tryInit();
    });
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
