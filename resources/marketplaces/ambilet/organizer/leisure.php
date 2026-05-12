<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Locație de agrement';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Locație de agrement</h1>
                <p class="mt-1 text-sm text-muted">
                    Configurare bilete, capacități zilnice, rapoarte fiscale și conținut pagină publică.
                </p>
            </div>
            <!-- Tab switcher -->
            <div class="inline-flex rounded-xl bg-white border border-border p-1">
                <button type="button" id="tab-btn-overview" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-primary text-white">Sumar & raport</button>
                <button type="button" id="tab-btn-products" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-muted hover:bg-slate-50">🎫 Produse</button>
                <button type="button" id="tab-btn-content" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-muted hover:bg-slate-50">Conținut pagină</button>
            </div>
        </div>

        <!-- State: no leisure events -->
        <div id="leisure-empty" class="hidden p-8 text-center bg-white border rounded-2xl border-border">
            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-slate-100 rounded-full">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10m14-10v10M9 21h6m-6 0a2 2 0 01-2-2v-4a2 2 0 012-2h6a2 2 0 012 2v4a2 2 0 01-2 2"/>
                </svg>
            </div>
            <h2 class="mb-2 text-lg font-semibold text-secondary">Niciun eveniment de tip „Locație de agrement"</h2>
            <p class="max-w-md mx-auto text-sm text-muted">
                Pentru a vedea date aici, creează un eveniment cu „Tip pagină" = „Locație de agrement".
                Fiecare tip de bilet poate fi atribuit societății principale sau secundare.
            </p>
        </div>

        <!-- Event picker (when more than one leisure event) -->
        <div id="leisure-event-picker" class="hidden mb-6">
            <label class="block mb-2 text-sm font-medium text-secondary">Eveniment</label>
            <select id="leisure-event-select" class="w-full max-w-md px-4 py-2 bg-white border rounded-lg border-border focus:outline-none focus:ring-2 focus:ring-primary">
            </select>
        </div>

        <!-- Loading -->
        <div id="leisure-loading" class="p-8 text-center">
            <div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div>
            <p class="mt-2 text-sm text-muted">Se încarcă...</p>
        </div>

        <!-- Tab: Produse (initial hidden) -->
        <div id="tab-products" class="hidden space-y-6">
            <div class="p-5 bg-white border rounded-2xl border-border flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center">🎫</div>
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Bilete și servicii</h2>
                        <p class="text-xs text-muted">Adaugă, editează sau dezactivează produsele oferite la locație.</p>
                    </div>
                </div>
                <button type="button" id="pr-add-btn" class="px-4 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark">+ Adaugă produs</button>
            </div>

            <div id="pr-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="pr-empty" class="hidden p-8 text-center bg-white border rounded-2xl border-border text-muted">Niciun produs încă. Apasă „Adaugă produs" ca să creezi primul bilet.</div>
            <div id="pr-list" class="hidden space-y-3"></div>
        </div>

        <!-- Modal produs -->
        <div id="pr-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl border border-border max-w-2xl w-full my-8 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="pr-modal-title" class="font-bold text-secondary text-xl">Produs</h3>
                    <button type="button" id="pr-modal-close" class="text-muted hover:text-secondary text-2xl leading-none">×</button>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Nume *</span>
                        <input id="pr-f-name" type="text" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: Bilet adult, Parcare auto, Ghidaj…">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Categorie serviciu *</span>
                        <select id="pr-f-category" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                            <option value="access">🎟️ Acces (bilet intrare)</option>
                            <option value="parking">🚗 Parcare</option>
                            <option value="rental">🛶 Închiriere echipament</option>
                            <option value="activity">🎯 Activitate cu operator</option>
                            <option value="extra">➕ Extra</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Societate emitentă</span>
                        <select id="pr-f-issuer" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                            <option value="primary">Principală</option>
                            <option value="secondary">Secundară</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Preț (RON) *</span>
                        <input id="pr-f-price" type="number" min="0" step="0.01" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="0.00">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Stoc total (opțional)</span>
                        <input id="pr-f-capacity" type="number" min="0" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="lăsa gol = nelimitat">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Capacitate zilnică (opțional)</span>
                        <input id="pr-f-dailycap" type="number" min="0" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: 500">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Durată serviciu (min)</span>
                        <input id="pr-f-duration" type="number" min="0" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: 60">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Iconiță (emoji)</span>
                        <input id="pr-f-icon" type="text" maxlength="6" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="🎟️">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Etichetă unitate</span>
                        <input id="pr-f-unit" type="text" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="bilet, persoană, oră, zi…">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">URL imagine card (opțional)</span>
                        <input id="pr-f-image" type="url" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="https://...">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Descriere scurtă</span>
                        <textarea id="pr-f-description" rows="2" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="Descriere..."></textarea>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Include (un element per linie)</span>
                        <textarea id="pr-f-includes" rows="3" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="Acces toată ziua&#10;Hartă tipărită&#10;Apă gratuită"></textarea>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Termeni utilizare</span>
                        <textarea id="pr-f-terms" rows="2" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="Condiții, restricții..."></textarea>
                    </label>
                    <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input id="pr-f-active" type="checkbox" class="w-4 h-4 accent-primary">
                            <span>Activ</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input id="pr-f-parking" type="checkbox" class="w-4 h-4 accent-primary">
                            <span>E parcare</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input id="pr-f-vehicle" type="checkbox" class="w-4 h-4 accent-primary">
                            <span>Cere date vehicul</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input id="pr-f-reqaccess" type="checkbox" class="w-4 h-4 accent-primary">
                            <span>Necesită bilet acces</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-between gap-2">
                    <button id="pr-f-delete" type="button" class="hidden px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-lg">🗑 Șterge</button>
                    <div class="ml-auto flex gap-2">
                        <button id="pr-f-cancel" type="button" class="px-3 py-2 text-sm border border-border rounded-lg hover:bg-slate-50">Renunță</button>
                        <button id="pr-f-save" type="button" class="px-5 py-2 text-sm bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark">Salvează</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Conținut pagină (initial hidden) -->
        <div id="tab-content" class="hidden space-y-6">
            <div class="p-5 bg-white border rounded-2xl border-border">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                        ✏️
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Editor conținut pagină publică</h2>
                        <p class="text-xs text-muted">Modificările apar imediat pe pagina publică după salvare.</p>
                    </div>
                </div>
            </div>

            <!-- HERO & IDENTITATE -->
            <details class="bg-white border rounded-2xl border-border" open>
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🎨 Hero & Identitate</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu principal</span>
                            <input type="text" data-vc="title_primary" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Lacul Sfânta Ana">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Subtitlu italic</span>
                            <input type="text" data-vc="title_secondary" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: & Tinovul Mohoș">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Kicker (text mic deasupra titlului)</span>
                        <input type="text" data-vc="hero_kicker" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Rezervație naturală protejată">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Badge-uri hero (separat prin virgulă)</span>
                        <input type="text" data-vc-list="hero_badges" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="🌿 Sit Natura 2000, 🏔️ Altitudine 950m, Jud. Harghita">
                        <span class="text-xs text-muted mt-1 block">Folosește emoji + text. Separă cu virgulă.</span>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu secțiune "Despre"</span>
                        <input type="text" data-vc="about_title" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Două cratere, o poveste">
                    </label>
                </div>
            </details>

            <!-- CONTACT & LINK-URI -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📞 Contact & Link-uri</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Telefon contact (cu prefix)</span>
                            <input type="text" data-vc="contact_phone" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="+40 752 171 050">
                            <span class="text-xs text-muted mt-1 block">Folosit pentru buton WhatsApp și bara info.</span>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">URL Google Maps</span>
                            <input type="url" data-vc="directions_url" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="https://maps.app.goo.gl/...">
                        </label>
                    </div>
                </div>
            </details>

            <!-- SAFETY WARNING -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>⚠️ Atenționare siguranță (afișată sub trasee)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-4 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Emoji</span>
                            <input type="text" data-vc-nested="safety_warning.icon" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="🐻" maxlength="6">
                        </label>
                        <label class="block md:col-span-3">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu</span>
                            <input type="text" data-vc-nested="safety_warning.title" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="Zonă cu urși">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Mesaj</span>
                        <textarea data-vc-nested="safety_warning.body" rows="3" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="Detalii recomandare..."></textarea>
                    </label>
                </div>
            </details>

            <!-- FAQ -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>❓ Întrebări frecvente (FAQ)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="faq-list" class="space-y-3"></div>
                    <button type="button" id="faq-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors">+ Adaugă întrebare</button>
                </div>
            </details>

            <!-- STATS HIGHLIGHTS -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📊 Statistici mari (numere)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="stats-list" class="space-y-3"></div>
                    <button type="button" id="stats-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors">+ Adaugă statistică</button>
                </div>
            </details>

            <!-- ATRACȚII -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🏞️ Atracții (carduri principale)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="attractions-list" class="space-y-3"></div>
                    <button type="button" id="attractions-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă atracție</button>
                </div>
            </details>

            <!-- TRASEE -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🥾 Trasee turistice</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="trails-list" class="space-y-3"></div>
                    <button type="button" id="trails-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă traseu</button>
                </div>
            </details>

            <!-- GALERIE -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📷 Galerie foto</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="gallery-list" class="space-y-2"></div>
                    <button type="button" id="gallery-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă imagine</button>
                </div>
            </details>

            <!-- VIDEO -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🎥 Video (YouTube / Vimeo)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="videos-list" class="space-y-2"></div>
                    <button type="button" id="videos-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă video</button>
                </div>
            </details>

            <!-- HARTĂ - centru + POIs -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📍 Hartă (centru + puncte de interes)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-3 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Latitudine centru</span>
                            <input type="text" data-vc-nested="map_config.center_lat" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="46.135">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Longitudine centru</span>
                            <input type="text" data-vc-nested="map_config.center_lng" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="25.778">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Zoom (1-18)</span>
                            <input type="number" min="1" max="18" data-vc-nested="map_config.zoom" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="13">
                        </label>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Puncte de interes pe hartă</p>
                        <div id="pois-list" class="space-y-2"></div>
                        <button type="button" id="pois-add" class="mt-2 px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă POI</button>
                    </div>
                </div>
            </details>

            <!-- HOTELURI -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🏨 Hoteluri / cazări apropiate</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="hotels-list" class="space-y-3"></div>
                    <button type="button" id="hotels-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă cazare</button>
                </div>
            </details>

            <!-- FLORA -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🌿 Floră & faună</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="flora-list" class="space-y-2"></div>
                    <button type="button" id="flora-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă specie</button>
                </div>
            </details>

            <!-- PROGRAM / SEZOANE -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📅 Program / sezoane operaționale</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="seasons-list" class="space-y-3"></div>
                    <button type="button" id="seasons-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă sezon</button>
                </div>
            </details>

            <!-- GETTING THERE -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🚗 Cum ajungi (instrucțiuni)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="getting-list" class="space-y-3"></div>
                    <button type="button" id="getting-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">+ Adaugă instrucțiune</button>
                </div>
            </details>

            <!-- INFO: pagina publică -->
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl text-xs text-blue-900">
                💡 Salvează apoi vezi modificările pe <a id="admin-edit-link" href="#" target="_blank" class="font-bold underline">pagina publică</a> (deschide cu <code class="bg-blue-100 px-1 rounded">?preview=1</code> pentru a vedea draft).
            </div>

            <!-- Save bar -->
            <div class="sticky bottom-4 z-30">
                <div class="bg-primary text-white rounded-2xl px-5 py-4 shadow-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span id="vc-save-status" class="text-sm font-medium">Modificările sunt salvate manual.</span>
                    </div>
                    <button type="button" id="vc-save-btn" class="px-5 py-2 bg-white text-primary font-bold rounded-lg hover:bg-primary-dark hover:text-white transition-colors disabled:opacity-50">
                        💾 Salvează
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab: Sumar & raport (existing) -->
        <div id="leisure-content" class="hidden space-y-6">
            <!-- Issuer overview -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div id="issuer-primary" class="p-5 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded text-primary bg-primary/10">
                            Societate principală
                        </span>
                    </div>
                    <p class="text-lg font-semibold text-secondary" data-issuer="primary.name">—</p>
                    <p class="text-sm text-muted" data-issuer="primary.tax_id"></p>
                    <p class="mt-1 text-xs text-muted" data-issuer="primary.address"></p>
                </div>
                <div id="issuer-secondary" class="hidden p-5 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded text-accent bg-accent/10">
                            Societate secundară
                        </span>
                    </div>
                    <p class="text-lg font-semibold text-secondary" data-issuer="secondary.name">—</p>
                    <p class="text-sm text-muted" data-issuer="secondary.tax_id"></p>
                    <p class="mt-1 text-xs text-muted" data-issuer="secondary.address"></p>
                </div>
            </div>

            <!-- Ticket types config -->
            <div class="bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-between px-5 py-4 border-b border-border">
                    <h2 class="font-semibold text-secondary">Tipuri de bilete și emitent</h2>
                    <a id="leisure-edit-event-link" href="#" target="_blank"
                       class="text-xs font-medium text-primary hover:underline">
                        Editează în panou Tixello →
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase bg-slate-50 text-muted">
                            <tr>
                                <th class="px-5 py-3 text-left">Bilet</th>
                                <th class="px-5 py-3 text-left">Categorie</th>
                                <th class="px-5 py-3 text-left">Capacitate / zi</th>
                                <th class="px-5 py-3 text-left">Emitent factură</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-rows" class="divide-y divide-border">
                            <tr><td class="px-5 py-4 text-center text-muted" colspan="4">Nu există tipuri de bilete.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports per issuer -->
            <div class="bg-white border rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-border">
                    <h2 class="font-semibold text-secondary">Vânzări pe societate emitentă</h2>
                    <div class="flex items-center gap-2">
                        <input id="leisure-from" type="date" class="px-3 py-1.5 text-sm bg-white border rounded-lg border-border">
                        <span class="text-xs text-muted">→</span>
                        <input id="leisure-to" type="date" class="px-3 py-1.5 text-sm bg-white border rounded-lg border-border">
                        <button id="leisure-refresh-report"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark">
                            Reîncarcă
                        </button>
                    </div>
                </div>
                <div id="leisure-report-rows" class="divide-y divide-border">
                    <div class="p-5 text-center text-sm text-muted">Selectează o perioadă și apasă „Reîncarcă".</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    let leisureEvents = [];
    let currentEventId = null;

    async function loadEvents() {
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const all = res.data || [];
            leisureEvents = all.filter((e) => (e.display_template || 'standard') === 'leisure_venue');

            if (leisureEvents.length === 0) {
                $('leisure-loading').classList.add('hidden');
                $('leisure-empty').classList.remove('hidden');
                return;
            }

            const select = $('leisure-event-select');
            select.innerHTML = leisureEvents.map((e) => {
                const title = e.name || (e.title && (e.title.ro || e.title.en || Object.values(e.title)[0])) || `Event #${e.id}`;
                return `<option value="${e.id}">${escapeHtml(title)} (#${e.id})</option>`;
            }).join('');

            if (leisureEvents.length > 1) {
                $('leisure-event-picker').classList.remove('hidden');
            }

            select.addEventListener('change', () => loadEventDetails(parseInt(select.value, 10)));
            await loadEventDetails(leisureEvents[0].id);
        } catch (err) {
            console.error('loadEvents error', err);
            $('leisure-loading').textContent = 'Eroare la încărcarea evenimentelor.';
        }
    }

    async function loadEventDetails(eventId) {
        currentEventId = eventId;
        $('leisure-loading').classList.remove('hidden');
        $('leisure-content').classList.add('hidden');

        try {
            const config = await AmbiletAPI.get(`/organizer/events/${eventId}/leisure/config`);
            renderConfig(config.data || {});

            // Update edit link
            const tixelloAdminUrl = (window.TIXELLO_ADMIN_URL || 'https://core.tixello.com');
            $('leisure-edit-event-link').href = `${tixelloAdminUrl}/marketplace/events/${eventId}/edit?tab=bilete`;

            // Default range: last 30 days
            const today = new Date();
            const past = new Date(today);
            past.setDate(today.getDate() - 30);
            $('leisure-from').value = past.toISOString().slice(0, 10);
            $('leisure-to').value = today.toISOString().slice(0, 10);

            await loadReport();
        } catch (err) {
            console.error('loadEventDetails error', err);
            $('leisure-loading').textContent = 'Eroare la încărcarea detaliilor evenimentului.';
            return;
        }

        $('leisure-loading').classList.add('hidden');
        $('leisure-content').classList.remove('hidden');
    }

    function renderConfig(data) {
        // Issuers
        const issuers = data.issuers || {};
        ['primary', 'secondary'].forEach((key) => {
            const block = $('issuer-' + key);
            const issuer = issuers[key];
            if (!issuer || !issuer.name) {
                if (key === 'secondary') block.classList.add('hidden');
                return;
            }
            if (key === 'secondary') block.classList.remove('hidden');
            block.querySelectorAll('[data-issuer]').forEach((el) => {
                const path = el.getAttribute('data-issuer').split('.');
                if (path[0] !== key) return;
                const value = issuer[path[1]];
                el.textContent = value || '';
            });
        });

        // Ticket types
        const types = data.ticket_types || [];
        const rows = $('ticket-types-rows');
        if (types.length === 0) {
            rows.innerHTML = '<tr><td class="px-5 py-4 text-center text-muted" colspan="4">Nu există tipuri de bilete.</td></tr>';
            return;
        }

        const categoryLabel = {
            access: 'Acces', parking: 'Parcare', rental: 'Închiriere',
            activity: 'Activitate', extra: 'Alt produs',
        };

        rows.innerHTML = types.map((tt) => {
            const cat = tt.service_category || 'access';
            const company = tt.issuing_company || 'primary';
            const issuerData = issuers[company] || issuers.primary || {};
            const issuerName = issuerData.name || (company === 'secondary' ? 'Societate secundară' : 'Societate principală');
            const companyBadge = company === 'secondary'
                ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-accent/10 text-accent">SECUNDARĂ</span>'
                : '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-primary/10 text-primary">PRINCIPALĂ</span>';
            const cap = tt.daily_capacity ? tt.daily_capacity + ' bilete/zi' : '<span class="text-muted">—</span>';
            return `
                <tr>
                    <td class="px-5 py-3 font-medium text-secondary">${escapeHtml(tt.name)}</td>
                    <td class="px-5 py-3 text-muted">${escapeHtml(categoryLabel[cat] || cat)}</td>
                    <td class="px-5 py-3 text-muted">${cap}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            ${companyBadge}
                            <span class="text-sm text-secondary">${escapeHtml(issuerName)}</span>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    async function loadReport() {
        if (!currentEventId) return;
        const from = $('leisure-from').value;
        const to = $('leisure-to').value;
        const params = new URLSearchParams({ from, to }).toString();

        const container = $('leisure-report-rows');
        container.innerHTML = '<div class="p-5 text-center text-sm text-muted">Se încarcă raportul...</div>';

        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/reports/by-issuer?${params}`);
            const rows = (res.data && res.data.rows) || [];
            const currency = (res.data && res.data.currency) || 'RON';

            if (rows.length === 0) {
                container.innerHTML = '<div class="p-5 text-center text-sm text-muted">Nicio vânzare în perioada selectată.</div>';
                return;
            }

            const categoryLabel = {
                access: 'Acces', parking: 'Parcare', rental: 'Închiriere',
                activity: 'Activitate', extra: 'Alt produs',
            };

            container.innerHTML = rows.map((row) => {
                const company = row.company || 'primary';
                const issuer = row.issuer || {};
                const issuerName = issuer.name || (company === 'secondary' ? 'Societate secundară' : 'Societate principală');
                const badge = company === 'secondary'
                    ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-accent/10 text-accent">SECUNDARĂ</span>'
                    : '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-primary/10 text-primary">PRINCIPALĂ</span>';
                const cif = issuer.tax_id ? ` (CIF ${escapeHtml(issuer.tax_id)})` : '';

                let categoriesHtml = '';
                if (row.by_category && Object.keys(row.by_category).length > 0) {
                    const items = Object.entries(row.by_category).map(([cat, info]) => {
                        return `<div class="flex justify-between text-xs">
                            <span class="text-muted">${escapeHtml(categoryLabel[cat] || cat)}</span>
                            <span class="font-medium text-secondary">${info.count} • ${parseFloat(info.subtotal).toFixed(2)} ${currency}</span>
                        </div>`;
                    }).join('');
                    categoriesHtml = `<div class="mt-3 pt-3 border-t border-border space-y-1">${items}</div>`;
                }

                return `
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                ${badge}
                                <span class="font-semibold text-secondary">${escapeHtml(issuerName)}${cif}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-secondary">${parseFloat(row.subtotal).toFixed(2)} ${currency}</p>
                                <p class="text-xs text-muted">${row.tickets_count} bilete • ${row.orders_count} comenzi</p>
                            </div>
                        </div>
                        ${categoriesHtml}
                    </div>`;
            }).join('');
        } catch (err) {
            console.error('loadReport error', err);
            container.innerHTML = '<div class="p-5 text-center text-sm text-error">Eroare la încărcarea raportului.</div>';
        }
    }

    // ========== TAB SWITCHING ==========
    function setupTabs() {
        const btnOverview = $('tab-btn-overview');
        const btnProducts = $('tab-btn-products');
        const btnContent = $('tab-btn-content');
        const overview = $('leisure-content');
        const products = $('tab-products');
        const content = $('tab-content');
        const empty = $('leisure-empty');

        if (!btnOverview || !btnContent) return;

        const tabs = [
            { btn: btnOverview, panel: overview, key: 'overview' },
            { btn: btnProducts, panel: products, key: 'products' },
            { btn: btnContent, panel: content, key: 'content' },
        ];

        function activate(which) {
            tabs.forEach(t => {
                if (!t.btn) return;
                const sel = t.key === which;
                t.btn.classList.toggle('bg-primary', sel);
                t.btn.classList.toggle('text-white', sel);
                t.btn.classList.toggle('text-muted', !sel);
                t.btn.classList.toggle('hover:bg-slate-50', !sel);
                if (t.panel) t.panel.classList.toggle('hidden', !sel);
            });
            empty.classList.add('hidden');
            if (which === 'overview') {
                if (leisureEvents.length === 0) { empty.classList.remove('hidden'); overview.classList.add('hidden'); }
                else if (!currentEventId) overview.classList.add('hidden');
            } else if (which === 'products') {
                loadProducts();
            } else if (which === 'content') {
                hydrateContentForm();
            }
        }

        btnOverview.addEventListener('click', () => activate('overview'));
        if (btnProducts) btnProducts.addEventListener('click', () => activate('products'));
        btnContent.addEventListener('click', () => activate('content'));
    }

    // ========== PRODUCTS CRUD ==========
    let productsCache = [];
    let editingProductId = null;

    const CAT_LABEL = { access: '🎟️ Acces', parking: '🚗 Parcare', rental: '🛶 Închiriere', activity: '🎯 Activitate', extra: '➕ Extra' };
    const CAT_COLOR = { access: 'blue', parking: 'violet', rental: 'amber', activity: 'emerald', extra: 'slate' };

    async function loadProducts() {
        if (!currentEventId) return;
        $('pr-loading').classList.remove('hidden');
        $('pr-list').classList.add('hidden');
        $('pr-empty').classList.add('hidden');
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/products`);
            productsCache = res.data?.products || [];
            renderProducts();
        } catch (e) {
            console.error('[leisure-products] load failed', e);
            $('pr-empty').textContent = 'Eroare: ' + (e?.message || '');
            $('pr-empty').classList.remove('hidden');
        } finally {
            $('pr-loading').classList.add('hidden');
        }
    }

    function renderProducts() {
        if (!productsCache.length) {
            $('pr-empty').classList.remove('hidden');
            $('pr-list').classList.add('hidden');
            return;
        }
        $('pr-empty').classList.add('hidden');
        $('pr-list').classList.remove('hidden');
        $('pr-list').innerHTML = productsCache.map(p => {
            const cat = p.service_category || 'access';
            const color = CAT_COLOR[cat] || 'slate';
            const icon = (p.meta?.icon) || '';
            const inactive = !p.is_active;
            return `<div class="bg-white border rounded-2xl border-border ${inactive ? 'opacity-60' : ''}">
                <div class="px-5 py-4 flex items-center gap-4">
                    <div class="text-2xl">${icon || '🎫'}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-secondary truncate">${escapeHtml(p.name)}</span>
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-${color}-100 text-${color}-800">${CAT_LABEL[cat] || cat}</span>
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">${p.issuing_company === 'secondary' ? 'SC2' : 'SC1'}</span>
                            ${inactive ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">INACTIV</span>' : ''}
                        </div>
                        ${p.description ? `<div class="text-xs text-muted mt-0.5 truncate">${escapeHtml(p.description)}</div>` : ''}
                        <div class="text-xs text-muted mt-1">
                            ${p.daily_capacity ? `📅 ${p.daily_capacity}/zi · ` : ''}${p.capacity ? `Stoc total: ${p.capacity}` : 'Stoc nelimitat'}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xl font-bold text-${color}-700">${Number(p.price).toFixed(2)}</div>
                        <div class="text-[10px] text-muted">${p.currency || 'RON'} / ${(p.meta?.unit_label) || 'buc'}</div>
                    </div>
                    <button data-edit-id="${p.id}" class="ml-2 px-3 py-1.5 text-xs font-medium border border-border rounded-lg hover:bg-slate-50">Editează</button>
                </div>
            </div>`;
        }).join('');
        $('pr-list').querySelectorAll('button[data-edit-id]').forEach(btn => {
            btn.addEventListener('click', () => openProductModal(parseInt(btn.dataset.editId, 10)));
        });
    }

    function openProductModal(id) {
        editingProductId = id;
        const p = id ? productsCache.find(x => x.id === id) : null;
        $('pr-modal-title').textContent = p ? 'Editează produs' : 'Adaugă produs';
        $('pr-f-name').value = p?.name || '';
        $('pr-f-category').value = p?.service_category || 'access';
        $('pr-f-issuer').value = p?.issuing_company || 'primary';
        $('pr-f-price').value = p ? Number(p.price).toFixed(2) : '';
        $('pr-f-capacity').value = p?.capacity || '';
        $('pr-f-dailycap').value = p?.daily_capacity || '';
        $('pr-f-duration').value = p?.service_duration_minutes || '';
        $('pr-f-icon').value = p?.meta?.icon || '';
        $('pr-f-unit').value = p?.meta?.unit_label || '';
        $('pr-f-image').value = p?.meta?.image || p?.meta?.image_url || '';
        $('pr-f-description').value = p?.description || '';
        $('pr-f-includes').value = Array.isArray(p?.meta?.includes) ? p.meta.includes.join('\n') : (p?.meta?.includes || '');
        $('pr-f-terms').value = p?.usage_terms || '';
        $('pr-f-active').checked = p ? !!p.is_active : true;
        $('pr-f-parking').checked = p ? !!p.is_parking : false;
        $('pr-f-vehicle').checked = p ? !!p.requires_vehicle_info : false;
        $('pr-f-reqaccess').checked = p ? !!p.requires_access_ticket : false;
        $('pr-f-delete').classList.toggle('hidden', !p);
        $('pr-modal').classList.remove('hidden');
        $('pr-modal').classList.add('flex');
    }
    function closeProductModal() {
        $('pr-modal').classList.add('hidden');
        $('pr-modal').classList.remove('flex');
        editingProductId = null;
    }

    async function saveProduct() {
        const includesText = $('pr-f-includes').value.trim();
        const includes = includesText ? includesText.split('\n').map(s => s.trim()).filter(Boolean) : [];
        const body = {
            name: $('pr-f-name').value.trim(),
            service_category: $('pr-f-category').value,
            issuing_company: $('pr-f-issuer').value,
            price: parseFloat($('pr-f-price').value) || 0,
            capacity: $('pr-f-capacity').value ? parseInt($('pr-f-capacity').value, 10) : null,
            daily_capacity: $('pr-f-dailycap').value ? parseInt($('pr-f-dailycap').value, 10) : null,
            service_duration_minutes: $('pr-f-duration').value ? parseInt($('pr-f-duration').value, 10) : null,
            description: $('pr-f-description').value.trim() || null,
            usage_terms: $('pr-f-terms').value.trim() || null,
            is_active: $('pr-f-active').checked,
            is_parking: $('pr-f-parking').checked,
            requires_vehicle_info: $('pr-f-vehicle').checked,
            requires_access_ticket: $('pr-f-reqaccess').checked,
            meta: {
                icon: $('pr-f-icon').value.trim() || null,
                unit_label: $('pr-f-unit').value.trim() || null,
                image: $('pr-f-image').value.trim() || null,
                includes,
            },
        };
        if (!body.name) { alert('Numele produsului e obligatoriu.'); return; }
        try {
            if (editingProductId) {
                await AmbiletAPI.put(`/organizer/events/${currentEventId}/leisure/products/${editingProductId}`, body);
            } else {
                await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/products`, body);
            }
            closeProductModal();
            loadProducts();
        } catch (e) {
            alert('Eroare salvare: ' + (e?.message || ''));
        }
    }

    async function deleteProduct() {
        if (!editingProductId) return;
        if (!confirm('Sigur ștergi acest produs? (Dacă există bilete vândute deja, doar dezactivează-l.)')) return;
        try {
            await AmbiletAPI.delete(`/organizer/events/${currentEventId}/leisure/products/${editingProductId}`);
            closeProductModal();
            loadProducts();
        } catch (e) {
            alert('Eroare ștergere: ' + (e?.message || ''));
        }
    }

    function setupProductsHandlers() {
        const addBtn = $('pr-add-btn'); if (addBtn) addBtn.addEventListener('click', () => openProductModal(null));
        const closeBtn = $('pr-modal-close'); if (closeBtn) closeBtn.addEventListener('click', closeProductModal);
        const cancelBtn = $('pr-f-cancel'); if (cancelBtn) cancelBtn.addEventListener('click', closeProductModal);
        const saveBtn = $('pr-f-save'); if (saveBtn) saveBtn.addEventListener('click', saveProduct);
        const delBtn = $('pr-f-delete'); if (delBtn) delBtn.addEventListener('click', deleteProduct);
        const modal = $('pr-modal'); if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeProductModal(); });
    }

    // ========== CONTENT EDITOR ==========
    let currentVenueConfig = {};

    function hydrateContentForm() {
        const ev = leisureEvents.find(e => e.id === currentEventId);
        if (!ev) return;
        currentVenueConfig = ev.venue_config || {};

        // Update public preview link
        const adminLink = $('admin-edit-link');
        if (adminLink && ev.slug) adminLink.href = `/bilete/${ev.slug}-${ev.id}?preview=1`;

        // Populate simple scalar fields
        document.querySelectorAll('[data-vc]').forEach((input) => {
            const key = input.dataset.vc;
            input.value = currentVenueConfig[key] || '';
        });

        // Populate list fields (CSV)
        document.querySelectorAll('[data-vc-list]').forEach((input) => {
            const key = input.dataset.vcList;
            const arr = Array.isArray(currentVenueConfig[key]) ? currentVenueConfig[key] : [];
            input.value = arr.join(', ');
        });

        // Populate nested fields (e.g. safety_warning.title)
        document.querySelectorAll('[data-vc-nested]').forEach((input) => {
            const path = input.dataset.vcNested.split('.');
            let val = currentVenueConfig;
            for (const p of path) val = (val && val[p]) ? val[p] : '';
            input.value = typeof val === 'string' ? val : '';
        });

        // Populate FAQ list
        const faqList = $('faq-list');
        if (faqList) {
            faqList.innerHTML = '';
            (currentVenueConfig.faqs || []).forEach((f, i) => faqList.appendChild(makeFaqRow(f.q || '', f.a || '', i)));
            if ((currentVenueConfig.faqs || []).length === 0) faqList.appendChild(makeFaqRow('', '', 0));
        }

        // Populate stats highlights
        const statsList = $('stats-list');
        if (statsList) {
            statsList.innerHTML = '';
            (currentVenueConfig.stats_highlights || []).forEach((s, i) => statsList.appendChild(makeStatRow(s.value || '', s.label || '', i)));
            if ((currentVenueConfig.stats_highlights || []).length === 0) statsList.appendChild(makeStatRow('', '', 0));
        }

        // Hydrate new repeaters
        hydrateRepeater('attractions-list', currentVenueConfig.attractions, makeAttractionRow);
        hydrateRepeater('trails-list', currentVenueConfig.trails, makeTrailRow);
        hydrateRepeater('gallery-list', currentVenueConfig.gallery, makeGalleryRow);
        hydrateRepeater('videos-list', currentVenueConfig.videos, makeVideoRow);
        hydrateRepeater('pois-list', currentVenueConfig.map_pois, makePoiRow);
        hydrateRepeater('hotels-list', currentVenueConfig.nearby_hotels, makeHotelRow);
        hydrateRepeater('flora-list', currentVenueConfig.flora_species, makeFloraRow);
        hydrateRepeater('seasons-list', currentVenueConfig.seasons, makeSeasonRow);
        hydrateRepeater('getting-list', currentVenueConfig.getting_there, makeGettingRow);
    }

    function hydrateRepeater(listId, data, factory) {
        const list = $(listId);
        if (!list) return;
        list.innerHTML = '';
        (Array.isArray(data) ? data : []).forEach((item) => list.appendChild(factory(item || {})));
    }

    function makeFaqRow(q, a, idx) {
        const wrap = document.createElement('div');
        wrap.className = 'p-3 bg-slate-50 rounded-lg space-y-2';
        wrap.innerHTML = `
            <div class="flex items-start gap-2">
                <input type="text" class="faq-q flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Întrebare" value="${escapeHtml(q)}">
                <button type="button" class="faq-remove p-2 text-error hover:bg-red-100 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
                </button>
            </div>
            <textarea class="faq-a w-full px-3 py-2 border border-border rounded-lg bg-white text-sm" rows="2" placeholder="Răspuns (poate conține HTML simplu)">${escapeHtml(a)}</textarea>
        `;
        wrap.querySelector('.faq-remove').addEventListener('click', () => wrap.remove());
        return wrap;
    }

    function makeStatRow(value, label) {
        const wrap = document.createElement('div');
        wrap.className = 'flex items-start gap-2';
        wrap.innerHTML = `
            <input type="text" class="stat-value flex-1 px-3 py-2 border border-border rounded-lg" placeholder="Valoare (ex: 30k, 950m)" value="${escapeHtml(value)}">
            <input type="text" class="stat-label flex-1 px-3 py-2 border border-border rounded-lg" placeholder="Etichetă (ex: Ani de la formare)" value="${escapeHtml(label)}">
            <button type="button" class="stat-remove p-2 text-error hover:bg-red-100 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
            </button>
        `;
        wrap.querySelector('.stat-remove').addEventListener('click', () => wrap.remove());
        return wrap;
    }

    // === Repeater row factories ===
    function repWrap(html, onRemove) {
        const wrap = document.createElement('div');
        wrap.className = 'p-3 bg-slate-50 rounded-lg';
        wrap.innerHTML = html;
        const rm = wrap.querySelector('[data-rm]');
        if (rm) rm.addEventListener('click', () => { if (onRemove) onRemove(wrap); wrap.remove(); });
        return wrap;
    }
    function repAttr(name, value) { return `data-rep="${name}" value="${escapeHtml(value ?? '')}"`; }

    function makeAttractionRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="icon" maxlength="6" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="🏞️" value="${escapeHtml(d.icon || '')}">
                <input type="text" data-rep="title" class="md:col-span-5 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Titlu atracție" value="${escapeHtml(d.title || '')}">
                <input type="url" data-rep="image" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="URL imagine (opțional)" value="${escapeHtml(d.image || '')}">
                <textarea data-rep="description" rows="2" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Descriere scurtă">${escapeHtml(d.description || '')}</textarea>
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function makeTrailRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="name" class="md:col-span-3 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Nume traseu" value="${escapeHtml(d.name || '')}">
                <select data-rep="difficulty" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white">
                    <option value="easy" ${d.difficulty==='easy'?'selected':''}>Ușor</option>
                    <option value="medium" ${d.difficulty==='medium'?'selected':''}>Mediu</option>
                    <option value="hard" ${d.difficulty==='hard'?'selected':''}>Dificil</option>
                </select>
                <input type="number" step="0.1" data-rep="distance_km" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="km" value="${d.distance_km ?? ''}">
                <input type="number" data-rep="duration_min" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="min" value="${d.duration_min ?? ''}">
                <input type="number" data-rep="elevation_m" class="md:col-span-2 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Diferență nivel (m)" value="${d.elevation_m ?? ''}">
                <input type="text" data-rep="start_point" class="md:col-span-4 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Punct de plecare" value="${escapeHtml(d.start_point || '')}">
                <textarea data-rep="description" rows="2" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Descriere">${escapeHtml(d.description || '')}</textarea>
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function makeGalleryRow(d) {
        return repWrap(`
            <div class="flex items-center gap-2">
                <input type="url" data-rep="url" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="URL imagine" value="${escapeHtml(d.url || d)}">
                <input type="text" data-rep="caption" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Caption (opțional)" value="${escapeHtml(d.caption || '')}">
                <button type="button" data-rm class="text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑</button>
            </div>
        `);
    }

    function makeVideoRow(d) {
        return repWrap(`
            <div class="flex items-center gap-2">
                <input type="url" data-rep="url" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="URL YouTube / Vimeo" value="${escapeHtml(d.url || d)}">
                <input type="text" data-rep="title" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Titlu (opțional)" value="${escapeHtml(d.title || '')}">
                <button type="button" data-rm class="text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑</button>
            </div>
        `);
    }

    function makePoiRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="lat" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Lat" value="${escapeHtml(d.lat ?? '')}">
                <input type="text" data-rep="lng" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Lng" value="${escapeHtml(d.lng ?? '')}">
                <input type="text" data-rep="icon" maxlength="6" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="📍" value="${escapeHtml(d.icon || '')}">
                <input type="text" data-rep="title" class="md:col-span-3 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Titlu POI" value="${escapeHtml(d.title || '')}">
                <input type="text" data-rep="description" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Descriere scurtă" value="${escapeHtml(d.description || '')}">
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function makeHotelRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="name" class="md:col-span-3 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Nume cazare" value="${escapeHtml(d.name || '')}">
                <input type="number" step="0.1" data-rep="distance_km" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Dist km" value="${d.distance_km ?? ''}">
                <input type="text" data-rep="stars" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Stele (ex: 4)" value="${escapeHtml(d.stars ?? '')}">
                <input type="url" data-rep="url" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Link" value="${escapeHtml(d.url || '')}">
                <input type="url" data-rep="image" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="URL imagine (opțional)" value="${escapeHtml(d.image || '')}">
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function makeFloraRow(d) {
        return repWrap(`
            <div class="flex items-start gap-2">
                <input type="text" data-rep="icon" maxlength="6" class="w-16 px-3 py-2 border border-border rounded-lg bg-white" placeholder="🌳" value="${escapeHtml(d.icon || '')}">
                <input type="text" data-rep="name" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Nume specie" value="${escapeHtml(d.name || '')}">
                <input type="text" data-rep="description" class="flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Detalii (opțional)" value="${escapeHtml(d.description || '')}">
                <button type="button" data-rm class="text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑</button>
            </div>
        `);
    }

    function makeSeasonRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="name" class="md:col-span-2 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Vară / Iarnă / Mai-Sept" value="${escapeHtml(d.name || '')}">
                <input type="text" data-rep="period" class="md:col-span-2 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Mai-Sept" value="${escapeHtml(d.period || '')}">
                <input type="time" data-rep="hours_start" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" value="${escapeHtml(d.hours_start || '')}">
                <input type="time" data-rep="hours_end" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" value="${escapeHtml(d.hours_end || '')}">
                <input type="text" data-rep="notes" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Note (ex: ultimă intrare 17:00)" value="${escapeHtml(d.notes || '')}">
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function makeGettingRow(d) {
        return repWrap(`
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
                <input type="text" data-rep="icon" maxlength="6" class="md:col-span-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="🚗" value="${escapeHtml(d.icon || '')}">
                <input type="text" data-rep="title" class="md:col-span-2 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Cu mașina" value="${escapeHtml(d.title || '')}">
                <input type="text" data-rep="from" class="md:col-span-3 px-3 py-2 border border-border rounded-lg bg-white" placeholder="De la / direcție" value="${escapeHtml(d.from || '')}">
                <textarea data-rep="description" rows="2" class="md:col-span-6 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Detalii rută">${escapeHtml(d.description || '')}</textarea>
            </div>
            <div class="text-right mt-2"><button type="button" data-rm class="text-xs text-rose-600 hover:bg-rose-100 px-2 py-1 rounded">🗑 Șterge</button></div>
        `);
    }

    function collectRepeater(listId) {
        const out = [];
        const list = $(listId);
        if (!list) return out;
        list.querySelectorAll(':scope > div').forEach((row) => {
            const item = {};
            row.querySelectorAll('[data-rep]').forEach((el) => {
                const key = el.dataset.rep;
                let val = el.value;
                if (typeof val === 'string') val = val.trim();
                if (val !== '' && val !== null && val !== undefined) item[key] = val;
            });
            if (Object.keys(item).length > 0) out.push(item);
        });
        return out;
    }

    function setNested(obj, pathStr, value) {
        const path = pathStr.split('.');
        let cur = obj;
        for (let i = 0; i < path.length - 1; i++) {
            if (!cur[path[i]] || typeof cur[path[i]] !== 'object') cur[path[i]] = {};
            cur = cur[path[i]];
        }
        cur[path[path.length - 1]] = value;
    }

    async function saveContent() {
        if (!currentEventId) return;
        const btn = $('vc-save-btn');
        const status = $('vc-save-status');
        btn.disabled = true;
        status.textContent = 'Se salvează...';

        const payload = {};

        // Scalar fields
        document.querySelectorAll('[data-vc]').forEach((input) => {
            payload[input.dataset.vc] = input.value.trim();
        });

        // List fields
        document.querySelectorAll('[data-vc-list]').forEach((input) => {
            const arr = input.value.split(',').map(s => s.trim()).filter(Boolean);
            payload[input.dataset.vcList] = arr;
        });

        // Nested fields
        document.querySelectorAll('[data-vc-nested]').forEach((input) => {
            setNested(payload, input.dataset.vcNested, input.value.trim());
        });

        // FAQ list
        const faqs = [];
        document.querySelectorAll('#faq-list > div').forEach((row) => {
            const q = row.querySelector('.faq-q')?.value.trim() || '';
            const a = row.querySelector('.faq-a')?.value.trim() || '';
            if (q || a) faqs.push({ q, a });
        });
        payload.faqs = faqs;

        // Stats highlights
        const stats = [];
        document.querySelectorAll('#stats-list > div').forEach((row) => {
            const value = row.querySelector('.stat-value')?.value.trim() || '';
            const label = row.querySelector('.stat-label')?.value.trim() || '';
            if (value || label) stats.push({ value, label });
        });
        payload.stats_highlights = stats;

        // New repeaters
        payload.attractions = collectRepeater('attractions-list');
        payload.trails = collectRepeater('trails-list');
        payload.gallery = collectRepeater('gallery-list');
        payload.videos = collectRepeater('videos-list');
        payload.map_pois = collectRepeater('pois-list');
        payload.nearby_hotels = collectRepeater('hotels-list');
        payload.flora_species = collectRepeater('flora-list');
        payload.seasons = collectRepeater('seasons-list');
        payload.getting_there = collectRepeater('getting-list');

        // Cast numeric fields in trails/hotels/POIs (Postgres expects numbers)
        payload.trails.forEach(t => {
            if (t.distance_km) t.distance_km = parseFloat(t.distance_km);
            if (t.duration_min) t.duration_min = parseInt(t.duration_min, 10);
            if (t.elevation_m) t.elevation_m = parseInt(t.elevation_m, 10);
        });
        payload.nearby_hotels.forEach(h => {
            if (h.distance_km) h.distance_km = parseFloat(h.distance_km);
            if (h.stars) h.stars = parseInt(h.stars, 10);
        });
        payload.map_pois.forEach(p => {
            if (p.lat) p.lat = parseFloat(p.lat);
            if (p.lng) p.lng = parseFloat(p.lng);
        });

        try {
            const res = await AmbiletAPI.put(`/organizer/events/${currentEventId}/leisure/venue-config`, { venue_config: payload });
            if (res.success) {
                status.textContent = '✓ Salvat. Recarcă pagina publică (?preview=1) pentru a vedea modificările.';
                status.classList.remove('text-red-200');
                // Update local cache
                currentVenueConfig = res.data?.venue_config || currentVenueConfig;
                const ev = leisureEvents.find(e => e.id === currentEventId);
                if (ev) ev.venue_config = currentVenueConfig;
                setTimeout(() => { status.textContent = 'Modificările sunt salvate manual.'; }, 4000);
            } else {
                status.textContent = '✗ Eroare: ' + (res.message || 'unknown');
                status.classList.add('text-red-200');
            }
        } catch (err) {
            console.error(err);
            status.textContent = '✗ Eroare conexiune';
            status.classList.add('text-red-200');
        }

        btn.disabled = false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('leisure-refresh-report').addEventListener('click', () => loadReport());
        $('faq-add')?.addEventListener('click', () => {
            const list = $('faq-list');
            if (list) list.appendChild(makeFaqRow('', '', list.children.length));
        });
        $('stats-add')?.addEventListener('click', () => {
            const list = $('stats-list');
            if (list) list.appendChild(makeStatRow('', '', list.children.length));
        });
        $('vc-save-btn')?.addEventListener('click', () => saveContent());

        // Generic "+ Adaugă" handlers pentru noile repeatere
        const repeaters = [
            ['attractions-add', 'attractions-list', makeAttractionRow],
            ['trails-add', 'trails-list', makeTrailRow],
            ['gallery-add', 'gallery-list', makeGalleryRow],
            ['videos-add', 'videos-list', makeVideoRow],
            ['pois-add', 'pois-list', makePoiRow],
            ['hotels-add', 'hotels-list', makeHotelRow],
            ['flora-add', 'flora-list', makeFloraRow],
            ['seasons-add', 'seasons-list', makeSeasonRow],
            ['getting-add', 'getting-list', makeGettingRow],
        ];
        repeaters.forEach(([btnId, listId, factory]) => {
            $(btnId)?.addEventListener('click', () => {
                const list = $(listId);
                if (list) list.appendChild(factory({}));
            });
        });

        setupTabs();
        setupProductsHandlers();
    });

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) {
            await new Promise((r) => setTimeout(r, 100));
            retries++;
        }
        if (typeof AmbiletAPI === 'undefined') {
            $('leisure-loading').textContent = 'API indisponibil.';
            return;
        }
        await loadEvents();
    });
})();
</script>

<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
