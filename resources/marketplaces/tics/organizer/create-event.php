<?php
/**
 * Organizer Create Event Page - Multi-step wizard
 */

// Load demo data for categories
$categories = [
    'Concert', 'Festival', 'Stand-up Comedy', 'Teatru', 'Sport', 'Conferinta', 'Workshop', 'Altele'
];
$eventTypes = [
    'physical' => 'Fizic (cu locatie)',
    'online' => 'Online (streaming)',
    'hybrid' => 'Hibrid'
];
$genres = [
    'Pop', 'Rock', 'Hip-Hop', 'Electronic', 'Jazz', 'Clasic', 'Folk', 'R&B', 'Metal', 'Alternative',
    'Dance', 'Reggae', 'Blues', 'Country', 'Latin', 'World Music', 'Experimental', 'Indie', 'Punk', 'Soul'
];

// Demo artists from internal library
$artists = [
    ['id' => 1, 'name' => 'Carla\'s Dreams', 'genre' => 'Pop', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=100&h=100&fit=crop'],
    ['id' => 2, 'name' => 'The Motans', 'genre' => 'Pop', 'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=100&h=100&fit=crop'],
    ['id' => 3, 'name' => 'Irina Rimes', 'genre' => 'Pop', 'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=100&h=100&fit=crop'],
    ['id' => 4, 'name' => 'Subcarpati', 'genre' => 'Folk/Hip-Hop', 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=100&h=100&fit=crop'],
    ['id' => 5, 'name' => 'Alternosfera', 'genre' => 'Rock', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=100&h=100&fit=crop'],
    ['id' => 6, 'name' => 'Vita de Vie', 'genre' => 'Rock', 'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=100&h=100&fit=crop'],
    ['id' => 7, 'name' => 'Parazitii', 'genre' => 'Hip-Hop', 'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=100&h=100&fit=crop'],
    ['id' => 8, 'name' => 'Smiley', 'genre' => 'Pop', 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=100&h=100&fit=crop'],
];

// Get pre-fill data from URL parameters
$prefill = [
    'name' => isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '',
    'short_description' => isset($_GET['short_description']) ? htmlspecialchars($_GET['short_description']) : '',
    'description' => isset($_GET['description']) ? htmlspecialchars($_GET['description']) : '',
    'category' => isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '',
    'genre' => isset($_GET['genre']) ? htmlspecialchars($_GET['genre']) : '',
    'event_type' => isset($_GET['event_type']) ? htmlspecialchars($_GET['event_type']) : '',
    'date_type' => isset($_GET['date_type']) ? htmlspecialchars($_GET['date_type']) : 'single',
    'start_date' => isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '',
    'start_time' => isset($_GET['start_time']) ? htmlspecialchars($_GET['start_time']) : '',
    'doors_time' => isset($_GET['doors_time']) ? htmlspecialchars($_GET['doors_time']) : '',
    'end_date' => isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '',
    'end_time' => isset($_GET['end_time']) ? htmlspecialchars($_GET['end_time']) : '',
    'venue_name' => isset($_GET['venue_name']) ? htmlspecialchars($_GET['venue_name']) : '',
    'address' => isset($_GET['address']) ? htmlspecialchars($_GET['address']) : '',
    'city' => isset($_GET['city']) ? htmlspecialchars($_GET['city']) : '',
    'postal_code' => isset($_GET['postal_code']) ? htmlspecialchars($_GET['postal_code']) : '',
    'status' => isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'published',
    'scheduled_at' => isset($_GET['scheduled_at']) ? htmlspecialchars($_GET['scheduled_at']) : '',
];

// Demo venues from internal library
$venues = [
    ['id' => 1, 'name' => 'Arena Nationala', 'address' => 'Bulevardul Basarabia 37-39', 'city' => 'Bucuresti', 'capacity' => 55000],
    ['id' => 2, 'name' => 'Sala Palatului', 'address' => 'Strada Ion Campineanu 28', 'city' => 'Bucuresti', 'capacity' => 4000],
    ['id' => 3, 'name' => 'Arenele Romane', 'address' => 'Bulevardul Basarabia 2', 'city' => 'Bucuresti', 'capacity' => 5000],
    ['id' => 4, 'name' => 'BT Arena', 'address' => 'Aleea Stadionului 2', 'city' => 'Cluj-Napoca', 'capacity' => 10000],
    ['id' => 5, 'name' => 'Polivalenta Bucuresti', 'address' => 'Bulevardul Tineretului 1', 'city' => 'Bucuresti', 'capacity' => 4500],
    ['id' => 6, 'name' => 'Fratelli Studios', 'address' => 'Strada Buzesti 50-52', 'city' => 'Bucuresti', 'capacity' => 1500],
    ['id' => 7, 'name' => 'Quantic Club', 'address' => 'Strada Zece Mese 12', 'city' => 'Bucuresti', 'capacity' => 800],
    ['id' => 8, 'name' => 'Form Space', 'address' => 'Strada Plevnei 53', 'city' => 'Cluj-Napoca', 'capacity' => 1000],
];

// Current page for sidebar (not used but included for consistency)
$currentPage = 'events';

// Page config for head
$pageTitle = 'Creeaza eveniment';
$pageDescription = 'Completeaza detaliile evenimentului';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Top Bar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Creeaza eveniment nou</h1>
                    <p class="text-sm text-gray-500">Pasul <span id="currentStepNum">1</span> din 5</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="saveDraft()" class="px-4 py-2 text-gray-600 text-sm font-medium hover:bg-gray-100 rounded-xl transition-colors">Salveaza ciorna</button>
                <button type="button" id="publishBtn" onclick="publishEvent()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors hidden">Publica</button>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <!-- Progress Steps -->
        <div class="flex items-center justify-center mb-10">
            <div class="flex items-center gap-3">
                <div class="step-item active flex items-center gap-2 cursor-pointer" data-step="1" onclick="goToStep(1)">
                    <span class="step-number w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-sm font-bold">1</span>
                    <span class="text-sm font-medium text-gray-900 hidden sm:inline">Detalii</span>
                </div>
                <div class="w-6 sm:w-10 h-px bg-gray-300 step-line" data-after="1"></div>
                <div class="step-item flex items-center gap-2 cursor-pointer" data-step="2" onclick="goToStep(2)">
                    <span class="step-number w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold">2</span>
                    <span class="text-sm text-gray-500 hidden sm:inline">Artisti</span>
                </div>
                <div class="w-6 sm:w-10 h-px bg-gray-300 step-line" data-after="2"></div>
                <div class="step-item flex items-center gap-2 cursor-pointer" data-step="3" onclick="goToStep(3)">
                    <span class="step-number w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold">3</span>
                    <span class="text-sm text-gray-500 hidden sm:inline">Bilete</span>
                </div>
                <div class="w-6 sm:w-10 h-px bg-gray-300 step-line" data-after="3"></div>
                <div class="step-item flex items-center gap-2 cursor-pointer" data-step="4" onclick="goToStep(4)">
                    <span class="step-number w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold">4</span>
                    <span class="text-sm text-gray-500 hidden sm:inline">Setari</span>
                </div>
                <div class="w-6 sm:w-10 h-px bg-gray-300 step-line" data-after="4"></div>
                <div class="step-item flex items-center gap-2 cursor-pointer" data-step="5" onclick="goToStep(5)">
                    <span class="step-number w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold">5</span>
                    <span class="text-sm text-gray-500 hidden sm:inline">Previzualizare</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: DETALII -->
        <div id="step1" class="step-content">
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Main Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Informatii de baza</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Numele evenimentului *</label>
                                <input type="text" id="eventName" value="<?= $prefill['name'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="ex: Concert Coldplay 2026" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descriere scurta *</label>
                                <input type="text" id="eventShortDesc" value="<?= $prefill['short_description'] ?>" maxlength="150" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="O propozitie care descrie evenimentul" required>
                                <p class="text-xs text-gray-500 mt-1"><span id="shortDescCount"><?= strlen($prefill['short_description']) ?></span>/150 caractere</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descriere completa</label>
                                <div id="descriptionEditor" class="border border-gray-200 rounded-xl overflow-hidden">
                                    <!-- Toolbar -->
                                    <div class="flex flex-wrap items-center gap-1 p-2 bg-gray-50 border-b border-gray-200">
                                        <button type="button" onclick="execDescCmd('bold')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Bold">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
                                        </button>
                                        <button type="button" onclick="execDescCmd('italic')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Italic">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 4h-9M14 20H5M15 4L9 20"/></svg>
                                        </button>
                                        <button type="button" onclick="execDescCmd('underline')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Underline">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3M4 21h16"/></svg>
                                        </button>
                                        <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                        <button type="button" onclick="execDescCmd('formatBlock', 'H2')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors text-xs font-bold" title="Heading">H2</button>
                                        <button type="button" onclick="execDescCmd('formatBlock', 'H3')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors text-xs font-bold" title="Subheading">H3</button>
                                        <button type="button" onclick="execDescCmd('formatBlock', 'P')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors text-xs" title="Paragraph">P</button>
                                        <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                        <button type="button" onclick="execDescCmd('insertUnorderedList')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Lista">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                        </button>
                                        <button type="button" onclick="execDescCmd('insertOrderedList')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Lista numerotata">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                                        </button>
                                        <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                        <button type="button" onclick="insertLink()" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Link">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                                        </button>
                                    </div>
                                    <!-- Editor -->
                                    <div id="descriptionContent" contenteditable="true" class="p-4 min-h-[200px] outline-none text-sm text-gray-700 prose prose-sm max-w-none" style="line-height: 1.6;"><?= $prefill['description'] ? $prefill['description'] : '<p>Descrie in detaliu evenimentul, ce pot astepta participantii, program etc.</p>' ?></div>
                                </div>
                            </div>
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Categorie *</label>
                                    <select id="eventCategory" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" required>
                                        <option value="">Selecteaza categoria</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars(strtolower($cat)) ?>" <?= $prefill['category'] === strtolower($cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gen eveniment</label>
                                    <select id="eventGenre" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                        <option value="">Selecteaza genul</option>
                                        <?php foreach ($genres as $genre): ?>
                                        <option value="<?= htmlspecialchars(strtolower($genre)) ?>" <?= $prefill['genre'] === strtolower($genre) ? 'selected' : '' ?>><?= htmlspecialchars($genre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Tip eveniment *</label>
                                    <select id="eventType" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" required>
                                        <option value="">Selecteaza tipul</option>
                                        <?php foreach ($eventTypes as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key) ?>" <?= $prefill['event_type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Data si ora</h2>
                        <div class="space-y-4">
                            <!-- Date Type Selection -->
                            <div class="flex items-center gap-4 mb-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="dateType" value="single" <?= $prefill['date_type'] !== 'range' ? 'checked' : '' ?> onchange="toggleDateType()" class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm text-gray-700">Zi unica</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="dateType" value="range" <?= $prefill['date_type'] === 'range' ? 'checked' : '' ?> onchange="toggleDateType()" class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm text-gray-700">Interval de date (festival, turneu)</span>
                                </label>
                            </div>

                            <!-- Single Day Fields -->
                            <div id="singleDayFields" class="<?= $prefill['date_type'] === 'range' ? 'hidden' : '' ?>">
                                <div class="grid sm:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Data eveniment *</label>
                                        <input type="date" id="startDate" value="<?= $prefill['start_date'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora deschidere usi</label>
                                        <input type="time" id="doorsTime" value="<?= $prefill['doors_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="18:00">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora inceput *</label>
                                        <input type="time" id="startTime" value="<?= $prefill['start_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" required>
                                    </div>
                                </div>
                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div class="sm:col-start-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora sfarsit (estimat)</label>
                                        <input type="time" id="endTime" value="<?= $prefill['end_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Date Range Fields -->
                            <div id="dateRangeFields" class="<?= $prefill['date_type'] === 'range' ? '' : 'hidden' ?>">
                                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Data inceput *</label>
                                        <input type="date" id="rangeStartDate" value="<?= $prefill['start_date'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Data sfarsit *</label>
                                        <input type="date" id="rangeEndDate" value="<?= $prefill['end_date'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                </div>
                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora deschidere usi (zilnic)</label>
                                        <input type="time" id="rangeDoorsTime" value="<?= $prefill['doors_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora inceput (zilnic)</label>
                                        <input type="time" id="rangeStartTime" value="<?= $prefill['start_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ora sfarsit (zilnic)</label>
                                        <input type="time" id="rangeEndTime" value="<?= $prefill['end_time'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-3">Pentru festivaluri sau evenimente pe mai multe zile, vei putea configura programul detaliat pe fiecare zi ulterior.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.2s">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Locatie</h2>
                        <div class="space-y-4">
                            <!-- Venue Search -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cauta locatie</label>
                                <div class="relative">
                                    <input type="text" id="venueSearch" class="input-field w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="Cauta in baza de date...">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <!-- Search Results Dropdown -->
                                <div id="venueResults" class="hidden mt-2 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto">
                                    <?php foreach ($venues as $venue): ?>
                                    <div class="venue-option p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                         data-id="<?= $venue['id'] ?>"
                                         data-name="<?= htmlspecialchars($venue['name']) ?>"
                                         data-address="<?= htmlspecialchars($venue['address']) ?>"
                                         data-city="<?= htmlspecialchars($venue['city']) ?>">
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($venue['name']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($venue['address']) ?>, <?= htmlspecialchars($venue['city']) ?> - <?= number_format($venue['capacity']) ?> locuri</p>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="p-3 border-t border-gray-200 bg-gray-50">
                                        <button type="button" onclick="showNewVenueForm()" class="flex items-center gap-2 text-indigo-600 font-medium text-sm hover:underline">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Adauga locatie noua
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Venue Display -->
                            <div id="selectedVenue" class="hidden p-4 bg-indigo-50 border border-indigo-200 rounded-xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900" id="selectedVenueName"></p>
                                        <p class="text-sm text-gray-500" id="selectedVenueAddress"></p>
                                    </div>
                                    <button type="button" onclick="clearSelectedVenue()" class="p-2 text-gray-400 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Manual Venue Form (shown when adding new or no venue selected) -->
                            <div id="manualVenueForm">
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="text-sm text-gray-500">sau completeaza manual:</span>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Nume locatie *</label>
                                        <input type="text" id="venueName" value="<?= $prefill['venue_name'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="ex: Arena Nationala">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresa *</label>
                                        <input type="text" id="venueAddress" value="<?= $prefill['address'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="Strada, numar">
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Oras *</label>
                                            <input type="text" id="venueCity" value="<?= $prefill['city'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="Bucuresti">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Cod postal</label>
                                            <input type="text" id="venueZip" value="<?= $prefill['postal_code'] ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="012345">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-gray-100 rounded-xl text-center">
                                <p class="text-sm text-gray-500 mb-2">Harta locatie</p>
                                <div class="w-full h-40 bg-gray-200 rounded-lg flex items-center justify-center">
                                    <span class="text-gray-400">Previzualizare harta</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.3s">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Imagini</h2>
                        <div class="space-y-6">
                            <!-- Cover Image -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Imagine principala (cover) *</label>
                                <p class="text-xs text-gray-500 mb-3">Imagine orizontala pentru banner si listari. Recomandat: 1920x1080px</p>
                                <div class="image-upload border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer transition-all" onclick="document.getElementById('coverImageInput').click()">
                                    <div id="coverImagePreview" class="hidden">
                                        <img id="coverImageImg" src="" class="max-h-40 mx-auto rounded-lg mb-3">
                                        <p class="text-sm text-gray-600">Click pentru a schimba</p>
                                    </div>
                                    <div id="coverImagePlaceholder">
                                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900">Click pentru upload sau trage imaginea aici</p>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG pana la 5MB</p>
                                    </div>
                                    <input type="file" id="coverImageInput" accept="image/*" class="hidden" onchange="previewImage(this, 'cover')">
                                </div>
                            </div>

                            <!-- Poster Image -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Imagine poster (vertical)</label>
                                <p class="text-xs text-gray-500 mb-3">Imagine verticala pentru afisaj si social media. Recomandat: 1080x1920px sau 1080x1350px</p>
                                <div class="image-upload border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer transition-all" onclick="document.getElementById('posterImageInput').click()">
                                    <div id="posterImagePreview" class="hidden">
                                        <img id="posterImageImg" src="" class="max-h-40 mx-auto rounded-lg mb-3">
                                        <p class="text-sm text-gray-600">Click pentru a schimba</p>
                                    </div>
                                    <div id="posterImagePlaceholder">
                                        <div class="w-12 h-16 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900">Click pentru upload poster</p>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG pana la 5MB</p>
                                    </div>
                                    <input type="file" id="posterImageInput" accept="image/*" class="hidden" onchange="previewImage(this, 'poster')">
                                </div>
                            </div>

                            <!-- Gallery -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Galerie imagini (optional)</label>
                                <div class="grid grid-cols-4 gap-3">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="image-upload aspect-square border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center cursor-pointer transition-all" onclick="document.getElementById('galleryImage<?= $i ?>').click()">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <input type="file" id="galleryImage<?= $i ?>" accept="image/*" class="hidden">
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Status -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp">
                        <h3 class="font-semibold text-gray-900 mb-4">Status publicare</h3>
                        <div class="space-y-3">
                            <label class="status-option flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors <?= $prefill['status'] === 'draft' ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' ?>">
                                <input type="radio" name="status" value="draft" class="w-4 h-4 text-indigo-600" <?= $prefill['status'] === 'draft' ? 'checked' : '' ?> onchange="updateStatusUI()">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Ciorna</p>
                                    <p class="text-xs text-gray-500">Salveaza fara a publica</p>
                                </div>
                            </label>
                            <label class="status-option flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors <?= $prefill['status'] === 'published' || $prefill['status'] === '' ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' ?>">
                                <input type="radio" name="status" value="published" class="w-4 h-4 text-indigo-600" <?= $prefill['status'] === 'published' || $prefill['status'] === '' ? 'checked' : '' ?> onchange="updateStatusUI()">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Publicat</p>
                                    <p class="text-xs text-gray-500">Vizibil public imediat</p>
                                </div>
                            </label>
                            <label class="status-option flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors <?= $prefill['status'] === 'scheduled' ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' ?>">
                                <input type="radio" name="status" value="scheduled" class="w-4 h-4 text-indigo-600" <?= $prefill['status'] === 'scheduled' ? 'checked' : '' ?> onchange="updateStatusUI()">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Programeaza</p>
                                    <p class="text-xs text-gray-500">Publica la o data ulterioara</p>
                                </div>
                            </label>
                            <!-- Schedule datetime picker -->
                            <div id="scheduleDatePicker" class="<?= $prefill['status'] === 'scheduled' ? '' : 'hidden' ?> mt-2 p-3 bg-gray-50 rounded-xl">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Data si ora publicarii</label>
                                <input type="datetime-local" id="scheduledAt" value="<?= $prefill['scheduled_at'] ?>" class="input-field w-full px-4 py-2 border border-gray-200 rounded-lg outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Quick Tips -->
                    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xl">💡</span>
                            <h3 class="font-semibold text-gray-900">Sfaturi</h3>
                        </div>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="text-green-500">✓</span>
                                Foloseste imagini de inalta calitate
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500">✓</span>
                                Scrie o descriere detaliata
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500">✓</span>
                                Adauga toate informatiile de acces
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500">✓</span>
                                Seteaza preturi competitive
                            </li>
                        </ul>
                    </div>

                    <!-- Preview Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.2s">
                        <h3 class="font-semibold text-gray-900 mb-4">Previzualizare</h3>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div id="previewImage" class="h-24 bg-gray-200 flex items-center justify-center">
                                <span class="text-gray-400 text-sm">Cover imagine</span>
                            </div>
                            <div class="p-3">
                                <p id="previewTitle" class="font-medium text-gray-400 text-sm">Titlu eveniment</p>
                                <p id="previewDetails" class="text-xs text-gray-300 mt-1">Data - Locatie</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: ARTISTI -->
        <div id="step2" class="step-content hidden">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">Artisti si performeri</h2>
                    <p class="text-sm text-gray-500 mb-6">Adauga artistii care vor participa la eveniment. Poti cauta in baza de date sau adauga artisti noi.</p>

                    <!-- Artist Search -->
                    <div class="relative mb-6">
                        <input type="text" id="artistSearch" class="input-field w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="Cauta artisti...">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                        <!-- Search Results Dropdown -->
                        <div id="artistResults" class="hidden absolute w-full mt-2 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto z-10">
                            <?php foreach ($artists as $artist): ?>
                            <div class="artist-option p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 flex items-center gap-3"
                                 data-id="<?= $artist['id'] ?>"
                                 data-name="<?= htmlspecialchars($artist['name']) ?>"
                                 data-genre="<?= htmlspecialchars($artist['genre']) ?>"
                                 data-image="<?= htmlspecialchars($artist['image']) ?>">
                                <img src="<?= htmlspecialchars($artist['image']) ?>" class="w-10 h-10 rounded-full object-cover" alt="">
                                <div>
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($artist['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($artist['genre']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="p-3 border-t border-gray-200 bg-gray-50">
                                <button type="button" onclick="showNewArtistModal()" class="flex items-center gap-2 text-indigo-600 font-medium text-sm hover:underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Adauga artist nou
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Artists -->
                    <div id="selectedArtists" class="space-y-3">
                        <p id="noArtistsText" class="text-center text-gray-400 py-8 border-2 border-dashed border-gray-200 rounded-xl">
                            Nu ai selectat niciun artist. Cauta si adauga artisti din campul de mai sus.
                        </p>
                    </div>
                </div>

                <!-- Lineup Order Info -->
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">🎤</span>
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-1">Ordinea artistilor</h3>
                            <p class="text-sm text-gray-600">Artistii sunt afisati in ordinea in care ii adaugi. Primul artist va fi afisat ca headliner principal. Poti trage pentru a reordona.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Artist Modal -->
        <div id="newArtistModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50" onclick="if(event.target === this) closeNewArtistModal()">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 animate-fadeInUp">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Adauga artist nou</h3>
                    <button type="button" onclick="closeNewArtistModal()" class="p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nume artist *</label>
                        <input type="text" id="newArtistName" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all" placeholder="ex: DJ Snake">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gen muzical</label>
                        <select id="newArtistGenre" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none transition-all">
                            <option value="">Selecteaza genul</option>
                            <?php foreach ($genres as $genre): ?>
                            <option value="<?= htmlspecialchars($genre) ?>"><?= htmlspecialchars($genre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Imagine artist</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:border-indigo-400 transition-colors" onclick="document.getElementById('newArtistImage').click()">
                            <div id="newArtistImagePreview" class="hidden">
                                <img id="newArtistImageImg" src="" class="w-20 h-20 rounded-full mx-auto object-cover mb-2">
                            </div>
                            <div id="newArtistImagePlaceholder">
                                <div class="w-12 h-12 bg-gray-100 rounded-full mx-auto mb-2 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <p class="text-sm text-gray-500">Click pentru upload</p>
                            </div>
                            <input type="file" id="newArtistImage" accept="image/*" class="hidden" onchange="previewNewArtistImage(this)">
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeNewArtistModal()" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">Anuleaza</button>
                    <button type="button" onclick="addNewArtist()" class="flex-1 px-4 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">Adauga</button>
                </div>
            </div>
        </div>

        <!-- STEP 3: BILETE -->
        <div id="step3" class="step-content hidden">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 animate-fadeInUp">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold text-gray-900">Categorii de bilete</h2>
                        <button type="button" onclick="addTicketCategory()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Adauga categorie
                        </button>
                    </div>
                    <div id="ticketCategories" class="space-y-4">
                        <!-- Default ticket category -->
                        <div class="ticket-category p-4 border border-gray-200 rounded-xl">
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Nume categorie *</label><input type="text" value="General Admission" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Pret (RON) *</label><input type="number" value="99" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-2">Cantitate *</label><input type="number" value="1000" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                            </div>
                            <div class="mt-3"><label class="block text-sm font-medium text-gray-700 mb-2">Descriere</label><input type="text" placeholder="Ce include aceasta categorie..." class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Cod promotional (optional)</h2>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Cod</label><input type="text" placeholder="ex: EARLY20" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none uppercase"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Reducere (%)</label><input type="number" placeholder="20" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Limita utilizari</label><input type="number" placeholder="100" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 4: SETARI -->
        <div id="step4" class="step-content hidden">
            <div class="max-w-3xl mx-auto space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Setari vanzari</h2>
                    <div class="space-y-4">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Inceput vanzari</label><input type="datetime-local" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">Sfarsit vanzari</label><input type="datetime-local" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Limita bilete per comanda</label><select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"><option>Fara limita</option><option>2 bilete</option><option>4 bilete</option><option selected>6 bilete</option><option>10 bilete</option></select></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Politica rambursare</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Permite rambursari</label>
                            <select id="refundPolicy" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                <option value="none">Nu permite rambursari</option>
                                <option value="7days" selected>Pana la 7 zile inainte de eveniment</option>
                                <option value="14days">Pana la 14 zile inainte de eveniment</option>
                                <option value="30days">Pana la 30 zile inainte de eveniment</option>
                                <option value="anytime">Oricand pana la inceperea evenimentului</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Termeni si conditii rambursare</label>
                            <p class="text-xs text-gray-500 mb-2">Descrie in detaliu conditiile de rambursare pentru clienti</p>
                            <div id="refundTermsEditor" class="border border-gray-200 rounded-xl overflow-hidden">
                                <!-- Toolbar -->
                                <div class="flex items-center gap-1 p-2 bg-gray-50 border-b border-gray-200">
                                    <button type="button" onclick="execCmd('bold')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Bold">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
                                    </button>
                                    <button type="button" onclick="execCmd('italic')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Italic">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 4h-9M14 20H5M15 4L9 20"/></svg>
                                    </button>
                                    <button type="button" onclick="execCmd('underline')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Underline">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3M4 21h16"/></svg>
                                    </button>
                                    <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                    <button type="button" onclick="execCmd('insertUnorderedList')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Lista">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                    </button>
                                    <button type="button" onclick="execCmd('insertOrderedList')" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Lista numerotata">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                                    </button>
                                </div>
                                <!-- Editor -->
                                <div id="refundTermsContent" contenteditable="true" class="p-4 min-h-[120px] outline-none text-sm text-gray-700" style="line-height: 1.6;">
                                    <p>Biletele pot fi returnate cu cel putin 7 zile inainte de data evenimentului.</p>
                                    <p><br></p>
                                    <p>Pentru solicitari de rambursare, va rugam sa ne contactati la adresa de email indicata in confirmarea comenzii.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 5: PREVIZUALIZARE -->
        <div id="step5" class="step-content hidden">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden animate-fadeInUp">
                    <div id="finalPreviewImage" class="h-64 bg-gray-200 flex items-center justify-center">
                        <span class="text-gray-400">Cover imagine</span>
                    </div>
                    <div class="p-6">
                        <h2 id="finalPreviewTitle" class="text-2xl font-bold text-gray-900 mb-2">Titlu eveniment</h2>
                        <p id="finalPreviewDesc" class="text-gray-600 mb-4">Descriere scurta...</p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <span id="finalPreviewDate">📅 Data</span>
                            <span id="finalPreviewLocation">📍 Locatie</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <p class="text-sm text-gray-500">Bilete de la</p>
                            <p id="finalPreviewPrice" class="text-2xl font-bold text-indigo-600">-- RON</p>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-2xl p-6 mt-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-green-800 mb-1">Gata de publicare!</h3>
                            <p class="text-sm text-green-700">Verifica detaliile si apasa butonul Publica pentru a face evenimentul vizibil.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Actions -->
        <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200">
            <button type="button" id="prevBtn" onclick="prevStep()" class="text-gray-600 hover:text-gray-900 font-medium hidden">← Inapoi</button>
            <a href="/organizator/evenimente" id="cancelBtn" class="text-gray-600 hover:text-gray-900 font-medium">Anuleaza</a>
            <div class="flex items-center gap-3">
                <button type="button" onclick="saveDraft()" class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">Salveaza ciorna</button>
                <button type="button" id="nextBtn" onclick="nextStep()" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors flex items-center gap-2">
                    Continua
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="button" id="publishBtnBottom" onclick="publishEvent()" class="hidden px-6 py-3 bg-green-600 text-white font-medium rounded-xl hover:bg-green-700 transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Publica evenimentul
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 5;
        let selectedVenueId = null;
        let selectedArtists = [];
        let newArtistIdCounter = 1000;

        // Venues and Artists data for JavaScript
        const venues = <?= json_encode($venues) ?>;
        const artists = <?= json_encode($artists) ?>;

        // Step navigation
        function goToStep(step) {
            if (step < 1 || step > totalSteps) return;
            if (step > currentStep && !validateCurrentStep()) return;

            currentStep = step;
            updateStepUI();
        }

        function nextStep() {
            if (!validateCurrentStep()) return;
            if (currentStep < totalSteps) {
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

        function validateCurrentStep() {
            if (currentStep === 1) {
                const name = document.getElementById('eventName').value.trim();
                const category = document.getElementById('eventCategory').value;
                const startDate = document.getElementById('startDate').value;

                if (!name) {
                    alert('Completeaza numele evenimentului');
                    return false;
                }
                if (!category) {
                    alert('Selecteaza o categorie');
                    return false;
                }
                if (!startDate) {
                    alert('Selecteaza data de inceput');
                    return false;
                }
            }
            return true;
        }

        function updateStepUI() {
            // Hide all step contents
            document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('step' + currentStep).classList.remove('hidden');

            // Update step indicators
            document.querySelectorAll('.step-item').forEach((item, index) => {
                const stepNum = index + 1;
                const stepNumber = item.querySelector('.step-number');
                const stepText = item.querySelector('span:last-child');

                item.classList.remove('active', 'completed');
                stepNumber.classList.remove('bg-indigo-600', 'bg-green-500', 'text-white');
                stepNumber.classList.add('bg-gray-200', 'text-gray-500');
                if (stepText) {
                    stepText.classList.remove('text-gray-900', 'font-medium');
                    stepText.classList.add('text-gray-500');
                }

                if (stepNum < currentStep) {
                    item.classList.add('completed');
                    stepNumber.classList.remove('bg-gray-200', 'text-gray-500');
                    stepNumber.classList.add('bg-green-500', 'text-white');
                } else if (stepNum === currentStep) {
                    item.classList.add('active');
                    stepNumber.classList.remove('bg-gray-200', 'text-gray-500');
                    stepNumber.classList.add('bg-indigo-600', 'text-white');
                    if (stepText) {
                        stepText.classList.remove('text-gray-500');
                        stepText.classList.add('text-gray-900', 'font-medium');
                    }
                }
            });

            // Update step lines
            document.querySelectorAll('.step-line').forEach(line => {
                const afterStep = parseInt(line.dataset.after);
                if (afterStep < currentStep) {
                    line.classList.remove('bg-gray-300');
                    line.classList.add('bg-green-500');
                } else {
                    line.classList.remove('bg-green-500');
                    line.classList.add('bg-gray-300');
                }
            });

            // Update current step number
            document.getElementById('currentStepNum').textContent = currentStep;

            // Update navigation buttons
            document.getElementById('prevBtn').classList.toggle('hidden', currentStep === 1);
            document.getElementById('cancelBtn').classList.toggle('hidden', currentStep > 1);
            document.getElementById('nextBtn').classList.toggle('hidden', currentStep === totalSteps);
            document.getElementById('publishBtnBottom').classList.toggle('hidden', currentStep !== totalSteps);
            document.getElementById('publishBtn').classList.toggle('hidden', currentStep !== totalSteps);

            // Update final preview on step 5
            if (currentStep === 5) {
                updateFinalPreview();
            }

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Venue search
        const venueSearch = document.getElementById('venueSearch');
        const venueResults = document.getElementById('venueResults');

        venueSearch.addEventListener('focus', function() {
            venueResults.classList.remove('hidden');
        });

        venueSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.venue-option').forEach(option => {
                const name = option.dataset.name.toLowerCase();
                const city = option.dataset.city.toLowerCase();
                option.classList.toggle('hidden', query && !name.includes(query) && !city.includes(query));
            });
        });

        // Venue selection
        document.querySelectorAll('.venue-option').forEach(option => {
            option.addEventListener('click', function() {
                selectVenue(this.dataset);
            });
        });

        function selectVenue(venue) {
            selectedVenueId = venue.id;
            document.getElementById('selectedVenueName').textContent = venue.name;
            document.getElementById('selectedVenueAddress').textContent = venue.address + ', ' + venue.city;
            document.getElementById('selectedVenue').classList.remove('hidden');
            document.getElementById('manualVenueForm').classList.add('hidden');
            venueResults.classList.add('hidden');
            venueSearch.value = '';

            // Fill hidden fields
            document.getElementById('venueName').value = venue.name;
            document.getElementById('venueAddress').value = venue.address;
            document.getElementById('venueCity').value = venue.city;

            // Update preview
            updatePreview();
        }

        function clearSelectedVenue() {
            selectedVenueId = null;
            document.getElementById('selectedVenue').classList.add('hidden');
            document.getElementById('manualVenueForm').classList.remove('hidden');
            document.getElementById('venueName').value = '';
            document.getElementById('venueAddress').value = '';
            document.getElementById('venueCity').value = '';
        }

        function showNewVenueForm() {
            clearSelectedVenue();
            venueResults.classList.add('hidden');
            document.getElementById('venueName').focus();
        }

        // Image preview
        function previewImage(input, type) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (type === 'cover') {
                        document.getElementById('coverImageImg').src = e.target.result;
                        document.getElementById('coverImagePreview').classList.remove('hidden');
                        document.getElementById('coverImagePlaceholder').classList.add('hidden');
                        document.getElementById('previewImage').innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-cover">';
                    } else if (type === 'poster') {
                        document.getElementById('posterImageImg').src = e.target.result;
                        document.getElementById('posterImagePreview').classList.remove('hidden');
                        document.getElementById('posterImagePlaceholder').classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // Live preview update
        document.getElementById('eventName').addEventListener('input', function(e) {
            const title = e.target.value || 'Titlu eveniment';
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewTitle').classList.toggle('text-gray-900', e.target.value);
            document.getElementById('previewTitle').classList.toggle('text-gray-400', !e.target.value);
        });

        document.getElementById('eventShortDesc').addEventListener('input', function(e) {
            document.getElementById('shortDescCount').textContent = e.target.value.length;
        });

        document.getElementById('startDate').addEventListener('change', updatePreview);
        document.getElementById('venueName').addEventListener('input', updatePreview);
        document.getElementById('venueCity').addEventListener('input', updatePreview);

        function updatePreview() {
            const date = document.getElementById('startDate').value;
            const venue = document.getElementById('venueName').value;
            const city = document.getElementById('venueCity').value;

            let details = [];
            if (date) {
                const d = new Date(date);
                details.push(d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }));
            }
            if (venue || city) {
                details.push([venue, city].filter(Boolean).join(', '));
            }

            document.getElementById('previewDetails').textContent = details.join(' - ') || 'Data - Locatie';
        }

        function updateFinalPreview() {
            const name = document.getElementById('eventName').value || 'Titlu eveniment';
            const desc = document.getElementById('eventShortDesc').value || 'Descriere scurta...';
            const date = document.getElementById('startDate').value;
            const venue = document.getElementById('venueName').value;
            const city = document.getElementById('venueCity').value;

            document.getElementById('finalPreviewTitle').textContent = name;
            document.getElementById('finalPreviewDesc').textContent = desc;

            if (date) {
                const d = new Date(date);
                document.getElementById('finalPreviewDate').textContent = '📅 ' + d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
            }

            if (venue || city) {
                document.getElementById('finalPreviewLocation').textContent = '📍 ' + [venue, city].filter(Boolean).join(', ');
            }

            // Get first ticket price
            const firstPrice = document.querySelector('.ticket-category input[type="number"]');
            if (firstPrice && firstPrice.value) {
                document.getElementById('finalPreviewPrice').textContent = firstPrice.value + ' RON';
            }

            // Copy cover image to final preview
            const coverImg = document.getElementById('coverImageImg');
            if (coverImg.src && coverImg.src !== window.location.href) {
                document.getElementById('finalPreviewImage').innerHTML = '<img src="' + coverImg.src + '" class="w-full h-full object-cover">';
            }
        }

        // Add ticket category
        function addTicketCategory() {
            const container = document.getElementById('ticketCategories');
            const newCategory = document.createElement('div');
            newCategory.className = 'ticket-category p-4 border border-gray-200 rounded-xl';
            newCategory.innerHTML = `
                <div class="flex items-start justify-between mb-3">
                    <span class="text-sm font-medium text-gray-500">Categorie noua</span>
                    <button type="button" onclick="this.closest('.ticket-category').remove()" class="p-1 text-gray-400 hover:text-red-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Nume categorie *</label><input type="text" placeholder="ex: VIP" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Pret (RON) *</label><input type="number" placeholder="149" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Cantitate *</label><input type="number" placeholder="500" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
                </div>
                <div class="mt-3"><label class="block text-sm font-medium text-gray-700 mb-2">Descriere</label><input type="text" placeholder="Ce include aceasta categorie..." class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"></div>
            `;
            container.appendChild(newCategory);
        }

        // Save draft
        function saveDraft() {
            alert('Ciorna a fost salvata! (Demo)');
        }

        // Publish event
        function publishEvent() {
            if (confirm('Esti sigur ca vrei sa publici evenimentul?')) {
                alert('Evenimentul a fost publicat cu succes! (Demo)');
                window.location.href = '/organizator/evenimente';
            }
        }

        // WYSIWYG editor command for refund terms
        function execCmd(command) {
            document.execCommand(command, false, null);
            document.getElementById('refundTermsContent').focus();
        }

        // WYSIWYG editor command for description
        function execDescCmd(command, value = null) {
            document.execCommand(command, false, value);
            document.getElementById('descriptionContent').focus();
        }

        // Insert link in WYSIWYG editor
        function insertLink() {
            const url = prompt('Introdu URL-ul:', 'https://');
            if (url) {
                document.execCommand('createLink', false, url);
            }
        }

        // Date type toggle
        function toggleDateType() {
            const singleDay = document.querySelector('input[name="dateType"][value="single"]').checked;
            document.getElementById('singleDayFields').classList.toggle('hidden', !singleDay);
            document.getElementById('dateRangeFields').classList.toggle('hidden', singleDay);
        }

        // Artist search functionality
        const artistSearch = document.getElementById('artistSearch');
        const artistResults = document.getElementById('artistResults');

        artistSearch.addEventListener('focus', function() {
            artistResults.classList.remove('hidden');
        });

        artistSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.artist-option').forEach(option => {
                const name = option.dataset.name.toLowerCase();
                const genre = option.dataset.genre.toLowerCase();
                const isSelected = selectedArtists.some(a => a.id == option.dataset.id);
                option.classList.toggle('hidden', isSelected || (query && !name.includes(query) && !genre.includes(query)));
            });
        });

        document.addEventListener('click', function(e) {
            if (!venueSearch.contains(e.target) && !venueResults.contains(e.target)) {
                venueResults.classList.add('hidden');
            }
            if (artistSearch && artistResults && !artistSearch.contains(e.target) && !artistResults.contains(e.target)) {
                artistResults.classList.add('hidden');
            }
        });

        // Artist selection
        document.querySelectorAll('.artist-option').forEach(option => {
            option.addEventListener('click', function() {
                selectArtist(this.dataset);
            });
        });

        function selectArtist(artist) {
            if (selectedArtists.some(a => a.id == artist.id)) return;

            selectedArtists.push({
                id: artist.id,
                name: artist.name,
                genre: artist.genre,
                image: artist.image
            });

            renderSelectedArtists();
            artistResults.classList.add('hidden');
            artistSearch.value = '';
        }

        function removeArtist(artistId) {
            selectedArtists = selectedArtists.filter(a => a.id != artistId);
            renderSelectedArtists();
        }

        function renderSelectedArtists() {
            const container = document.getElementById('selectedArtists');
            const noArtistsText = document.getElementById('noArtistsText');

            if (selectedArtists.length === 0) {
                container.innerHTML = '<p id="noArtistsText" class="text-center text-gray-400 py-8 border-2 border-dashed border-gray-200 rounded-xl">Nu ai selectat niciun artist. Cauta si adauga artisti din campul de mai sus.</p>';
                return;
            }

            let html = '';
            selectedArtists.forEach((artist, index) => {
                const isHeadliner = index === 0;
                html += `
                    <div class="artist-card flex items-center gap-4 p-4 bg-${isHeadliner ? 'indigo-50 border-indigo-200' : 'gray-50 border-gray-200'} border rounded-xl" data-id="${artist.id}">
                        <div class="flex items-center gap-1 text-gray-400 cursor-move" title="Trage pentru a reordona">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        </div>
                        <img src="${artist.image || 'https://via.placeholder.com/40'}" class="w-12 h-12 rounded-full object-cover" alt="">
                        <div class="flex-1">
                            <p class="font-medium text-gray-900">${artist.name}</p>
                            <p class="text-xs text-gray-500">${artist.genre || 'Gen necunoscut'}${isHeadliner ? ' • <span class="text-indigo-600 font-medium">Headliner</span>' : ''}</p>
                        </div>
                        <button type="button" onclick="removeArtist(${artist.id})" class="p-2 text-gray-400 hover:text-red-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        // New artist modal
        function showNewArtistModal() {
            document.getElementById('newArtistModal').classList.remove('hidden');
            document.getElementById('newArtistModal').classList.add('flex');
            artistResults.classList.add('hidden');
        }

        function closeNewArtistModal() {
            document.getElementById('newArtistModal').classList.add('hidden');
            document.getElementById('newArtistModal').classList.remove('flex');
            // Reset form
            document.getElementById('newArtistName').value = '';
            document.getElementById('newArtistGenre').value = '';
            document.getElementById('newArtistImagePreview').classList.add('hidden');
            document.getElementById('newArtistImagePlaceholder').classList.remove('hidden');
        }

        function previewNewArtistImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('newArtistImageImg').src = e.target.result;
                    document.getElementById('newArtistImagePreview').classList.remove('hidden');
                    document.getElementById('newArtistImagePlaceholder').classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function addNewArtist() {
            const name = document.getElementById('newArtistName').value.trim();
            const genre = document.getElementById('newArtistGenre').value;
            const imagePreview = document.getElementById('newArtistImageImg').src;

            if (!name) {
                alert('Introdu numele artistului');
                return;
            }

            const newArtist = {
                id: newArtistIdCounter++,
                name: name,
                genre: genre,
                image: imagePreview && imagePreview !== window.location.href ? imagePreview : 'https://via.placeholder.com/100'
            };

            selectedArtists.push(newArtist);
            renderSelectedArtists();
            closeNewArtistModal();
        }

        // Status UI update
        function updateStatusUI() {
            document.querySelectorAll('.status-option').forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (radio.checked) {
                    option.classList.remove('border-gray-200');
                    option.classList.add('border-indigo-200', 'bg-indigo-50');
                } else {
                    option.classList.remove('border-indigo-200', 'bg-indigo-50');
                    option.classList.add('border-gray-200');
                }
            });

            // Toggle schedule date picker
            const scheduledRadio = document.querySelector('input[name="status"][value="scheduled"]');
            const datePicker = document.getElementById('scheduleDatePicker');
            if (scheduledRadio && scheduledRadio.checked) {
                datePicker.classList.remove('hidden');
            } else {
                datePicker.classList.add('hidden');
            }
        }

        // Initialize preview with prefilled values on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update preview title if prefilled
            const nameInput = document.getElementById('eventName');
            if (nameInput.value) {
                document.getElementById('previewTitle').textContent = nameInput.value;
                document.getElementById('previewTitle').classList.add('text-gray-900');
                document.getElementById('previewTitle').classList.remove('text-gray-400');
            }

            // Update preview details
            updatePreview();
        });
    </script>
</body>
</html>
