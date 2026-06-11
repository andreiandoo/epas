<?php
/**
 * Organizer Event Edit Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$events = $demoData['events'];

// Get event ID from URL (demo: use first active event)
$eventId = isset($_GET['id']) ? $_GET['id'] : null;
$event = null;
foreach ($events as $e) {
    if ($e['status'] === 'active') {
        $event = $e;
        break;
    }
}

// Demo event data (extended)
$eventData = [
    'id' => $event['id'] ?? 'evt-001',
    'name' => $event['name'] ?? 'Coldplay: Music of the Spheres',
    'short_description' => 'Cel mai asteptat concert al anului vine in Bucuresti!',
    'description' => "Coldplay revine in Romania cu turneul mondial \"Music of the Spheres\"!\n\nProgram:\n- 18:00 - Deschidere porti\n- 19:30 - Artist invitat\n- 21:00 - Coldplay\n\nInclus in bilet: acces concert, bratara LED interactiva.",
    'category' => 'Concert',
    'event_type' => 'physical',
    'start_date' => '2026-02-14',
    'start_time' => '18:00',
    'end_date' => '2026-02-14',
    'end_time' => '23:30',
    'venue_name' => 'Arena Nationala',
    'address' => 'Bulevardul Basarabia 37-39, Sector 2',
    'city' => 'Bucuresti',
    'postal_code' => '022103',
    'image' => $event['image'] ?? 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=400&fit=crop',
    'status' => 'published',
    'tickets_sold' => $event['ticketsSold'] ?? 1245,
    'tickets_total' => $event['ticketsTotal'] ?? 5000,
    'revenue' => $event['revenue'] ?? 87150,
    'ticket_categories' => [
        [
            'name' => 'General Admission',
            'description' => 'Acces zona generala',
            'price' => 149,
            'quantity' => 3000,
            'sold' => 892,
            'status' => 'active',
            'icon_bg' => 'indigo'
        ],
        [
            'name' => 'VIP Experience',
            'description' => 'Loc rezervat, meet & greet',
            'price' => 499,
            'quantity' => 500,
            'sold' => 253,
            'status' => 'active',
            'icon_bg' => 'purple'
        ],
        [
            'name' => 'Early Bird',
            'description' => 'Pret redus (limitat)',
            'price' => 99,
            'quantity' => 100,
            'sold' => 100,
            'status' => 'soldout',
            'icon_bg' => 'amber'
        ]
    ],
    'promo_codes' => [
        ['code' => 'WELCOME10', 'discount' => '10%', 'uses' => 45, 'status' => 'active']
    ]
];

// Check if event is active/published (cannot modify venue/dates)
$isActiveEvent = $eventData['status'] === 'published' || $eventData['status'] === 'active';

// Current page for sidebar (not used but included for consistency)
$currentPage = 'events';

// Page config for head
$pageTitle = 'Editeaza eveniment';
$pageDescription = htmlspecialchars($eventData['name']);

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Editeaza eveniment</h1>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($eventData['name']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">Publicat</span>
                <a href="/eveniment/<?= htmlspecialchars($eventData['id']) ?>" target="_blank" class="px-4 py-2 text-gray-600 text-sm font-medium hover:bg-gray-100 rounded-xl">Previzualizare</a>
                <button onclick="saveChanges()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Salveaza</button>
            </div>
        </div>
        <!-- Tabs -->
        <div class="max-w-6xl mx-auto px-4 flex gap-2">
            <button onclick="switchTab('details')" class="tab-btn active px-4 py-3 text-sm font-medium rounded-t-xl border-b-2 border-transparent" data-tab="details">Detalii</button>
            <button onclick="switchTab('tickets')" class="tab-btn px-4 py-3 text-sm font-medium text-gray-500 rounded-t-xl border-b-2 border-transparent hover:text-gray-700" data-tab="tickets">Bilete</button>
            <button onclick="switchTab('marketing')" class="tab-btn px-4 py-3 text-sm font-medium text-gray-500 rounded-t-xl border-b-2 border-transparent hover:text-gray-700" data-tab="marketing">Marketing</button>
            <button onclick="switchTab('promo')" class="tab-btn px-4 py-3 text-sm font-medium text-gray-500 rounded-t-xl border-b-2 border-transparent hover:text-gray-700" data-tab="promo">Promo</button>
            <button onclick="switchTab('settings')" class="tab-btn px-4 py-3 text-sm font-medium text-gray-500 rounded-t-xl border-b-2 border-transparent hover:text-gray-700" data-tab="settings">Setari</button>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- TAB 1: DETALII -->
        <div id="tab-details" class="tab-content active">
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Informatii de baza</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Numele evenimentului *</label>
                                <input type="text" value="<?= htmlspecialchars($eventData['name']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descriere scurta *</label>
                                <input type="text" value="<?= htmlspecialchars($eventData['short_description']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                <p class="text-xs text-gray-500 mt-1"><?= strlen($eventData['short_description']) ?>/150 caractere</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descriere completa</label>
                                <textarea rows="6" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none resize-none"><?= htmlspecialchars($eventData['description']) ?></textarea>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Categorie</label>
                                    <select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                        <option <?= $eventData['category'] === 'Concert' ? 'selected' : '' ?>>Concert</option>
                                        <option <?= $eventData['category'] === 'Festival' ? 'selected' : '' ?>>Festival</option>
                                        <option <?= $eventData['category'] === 'Stand-up Comedy' ? 'selected' : '' ?>>Stand-up Comedy</option>
                                        <option <?= $eventData['category'] === 'Teatru' ? 'selected' : '' ?>>Teatru</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Tip eveniment</label>
                                    <select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                        <option value="physical" <?= $eventData['event_type'] === 'physical' ? 'selected' : '' ?>>Fizic (cu locatie)</option>
                                        <option value="online" <?= $eventData['event_type'] === 'online' ? 'selected' : '' ?>>Online</option>
                                        <option value="hybrid" <?= $eventData['event_type'] === 'hybrid' ? 'selected' : '' ?>>Hibrid</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 <?= $isActiveEvent ? 'opacity-75' : '' ?>">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Data si ora</h2>
                            <?php if ($isActiveEvent): ?>
                            <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">Blocat</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActiveEvent): ?>
                        <p class="text-sm text-amber-600 mb-4">Nu poti modifica data si ora pentru un eveniment publicat.</p>
                        <?php endif; ?>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Data inceput *</label>
                                <input type="date" value="<?= htmlspecialchars($eventData['start_date']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ora inceput *</label>
                                <input type="time" value="<?= htmlspecialchars($eventData['start_time']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Data sfarsit</label>
                                <input type="date" value="<?= htmlspecialchars($eventData['end_date']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ora sfarsit</label>
                                <input type="time" value="<?= htmlspecialchars($eventData['end_time']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 <?= $isActiveEvent ? 'opacity-75' : '' ?>">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Locatie</h2>
                            <?php if ($isActiveEvent): ?>
                            <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">Blocat</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActiveEvent): ?>
                        <p class="text-sm text-amber-600 mb-4">Nu poti modifica locatia pentru un eveniment publicat.</p>
                        <?php endif; ?>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nume locatie *</label>
                                <input type="text" value="<?= htmlspecialchars($eventData['venue_name']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Adresa *</label>
                                <input type="text" value="<?= htmlspecialchars($eventData['address']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Oras *</label>
                                    <input type="text" value="<?= htmlspecialchars($eventData['city']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Cod postal</label>
                                    <input type="text" value="<?= htmlspecialchars($eventData['postal_code']) ?>" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none <?= $isActiveEvent ? 'bg-gray-100 cursor-not-allowed' : '' ?>" <?= $isActiveEvent ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Imagini</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Imagine principala</label>
                            <div class="relative">
                                <img src="<?= htmlspecialchars($eventData['image']) ?>" class="w-full h-48 object-cover rounded-xl">
                                <button class="absolute top-2 right-2 p-2 bg-black/50 text-white rounded-lg hover:bg-black/70">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-6">
                    <!-- Event Preview -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="relative">
                            <img src="<?= htmlspecialchars($eventData['image']) ?>" class="w-full h-32 object-cover" alt="Event preview">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                            <div class="absolute bottom-3 left-3 right-3">
                                <p class="text-white font-semibold text-sm truncate"><?= htmlspecialchars($eventData['name']) ?></p>
                                <p class="text-white/80 text-xs"><?= date('d M Y', strtotime($eventData['start_date'])) ?> - <?= htmlspecialchars($eventData['venue_name']) ?></p>
                            </div>
                        </div>
                        <div class="p-4">
                            <a href="/eveniment/<?= htmlspecialchars($eventData['id']) ?>" target="_blank" class="flex items-center justify-center gap-2 w-full py-2 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-xl hover:bg-indigo-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Vezi pagina evenimentului
                            </a>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Status</h3>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 p-3 border border-green-200 bg-green-50 rounded-xl cursor-pointer">
                                <input type="radio" name="status" value="published" checked class="w-4 h-4 text-indigo-600">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Publicat</p>
                                    <p class="text-xs text-gray-500">Vizibil public</p>
                                </div>
                            </label>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs text-gray-500 mb-3">Actiuni status</p>
                            <div class="space-y-2">
                                <button type="button" onclick="toggleSoldOut()" id="btn-sold-out" class="w-full flex items-center gap-3 p-3 border border-gray-200 rounded-xl hover:bg-amber-50 hover:border-amber-300 transition-colors text-left">
                                    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Marcheaza ca sold out</p>
                                        <p class="text-xs text-gray-500">Opreste vanzarile</p>
                                    </div>
                                </button>
                                <button type="button" onclick="showPostponedModal()" id="btn-postponed" class="w-full flex items-center gap-3 p-3 border border-gray-200 rounded-xl hover:bg-orange-50 hover:border-orange-300 transition-colors text-left">
                                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Amana evenimentul</p>
                                        <p class="text-xs text-gray-500">Seteaza o noua data</p>
                                    </div>
                                </button>
                                <button type="button" onclick="showCancelledModal()" id="btn-cancelled" class="w-full flex items-center gap-3 p-3 border border-gray-200 rounded-xl hover:bg-red-50 hover:border-red-300 transition-colors text-left">
                                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Anuleaza evenimentul</p>
                                        <p class="text-xs text-gray-500">Notifica participantii</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Statistici</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Bilete vandute</span>
                                <span class="font-bold"><?= number_format($eventData['tickets_sold']) ?> / <?= number_format($eventData['tickets_total']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: <?= round($eventData['tickets_sold'] / $eventData['tickets_total'] * 100) ?>%"></div>
                            </div>
                            <div class="flex justify-between pt-2">
                                <span class="text-sm text-gray-500">Venituri</span>
                                <span class="font-bold text-green-600"><?= number_format($eventData['revenue'], 0, ',', '.') ?> RON</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: BILETE -->
        <div id="tab-tickets" class="tab-content">
            <div class="space-y-6">
                <div class="grid sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-2xl font-bold"><?= number_format($eventData['tickets_total']) ?></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Vandute</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($eventData['tickets_sold']) ?></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Disponibile</p>
                        <p class="text-2xl font-bold text-indigo-600"><?= number_format($eventData['tickets_total'] - $eventData['tickets_sold'] - 50) ?></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-sm text-gray-500">Rezervate</p>
                        <p class="text-2xl font-bold text-amber-600">50</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200">
                    <div class="flex items-center justify-between p-6 border-b border-gray-100">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Categorii de bilete</h2>
                            <?php if ($isActiveEvent): ?>
                            <p class="text-sm text-amber-600 mt-1">Pentru un eveniment publicat poti modifica doar stocul disponibil.</p>
                            <?php endif; ?>
                        </div>
                        <button onclick="document.getElementById('addTicketModal').classList.remove('hidden')" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Adauga
                        </button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($eventData['ticket_categories'] as $index => $ticket): ?>
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 bg-<?= $ticket['icon_bg'] ?>-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-<?= $ticket['icon_bg'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($ticket['name']) ?></h3>
                                        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($ticket['description']) ?></p>
                                        <div class="flex items-center gap-4 mt-2">
                                            <span class="text-lg font-bold"><?= number_format($ticket['price']) ?> RON</span>
                                            <?php if ($ticket['status'] === 'soldout'): ?>
                                            <span class="text-sm text-red-600"><?= number_format($ticket['sold']) ?> vandute (SOLD OUT)</span>
                                            <?php else: ?>
                                            <span class="text-sm text-green-600"><?= number_format($ticket['sold']) ?> vandute</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($ticket['status'] === 'soldout'): ?>
                                    <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full">Sold Out</span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Activ</span>
                                    <?php if (!$isActiveEvent): ?>
                                    <button class="p-2 text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isActiveEvent): ?>
                            <!-- Stock and sold-out controls for active events -->
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <div class="flex items-center gap-4 flex-wrap">
                                    <?php if ($ticket['status'] !== 'soldout'): ?>
                                    <div class="flex-1 min-w-[200px]">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Stoc disponibil</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" id="ticketStock_<?= $index ?>" value="<?= $ticket['quantity'] - $ticket['sold'] ?>" min="0" class="w-32 px-3 py-2 border border-gray-200 rounded-lg text-center font-medium" onchange="updateTicketStock(<?= $index ?>, this.value)">
                                            <span class="text-sm text-gray-500">bilete ramase</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-3">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" id="soldout_<?= $index ?>" <?= $ticket['status'] === 'soldout' ? 'checked' : '' ?> onchange="toggleSoldOut(<?= $index ?>, this.checked)" class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                            <span class="text-sm font-medium text-gray-700">Sold Out</span>
                                        </label>
                                        <button onclick="saveTicketStock(<?= $index ?>)" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Salveaza</button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Coduri promotionale</h2>
                        <button onclick="document.getElementById('addPromoModal').classList.remove('hidden')" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Adauga cod
                        </button>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($eventData['promo_codes'] as $promo): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <code class="font-mono font-bold text-gray-900"><?= htmlspecialchars($promo['code']) ?></code>
                                        <button onclick="copyPromoCode('<?= htmlspecialchars($promo['code']) ?>')" class="p-1 rounded hover:bg-gray-200">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                    </div>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($promo['discount']) ?> reducere - <?= $promo['uses'] ?> utilizari</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Activ</span>
                                <button class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($eventData['promo_codes'])): ?>
                    <div class="text-center py-6 text-gray-500">
                        <p class="text-sm">Niciun cod promotional activ</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Invitatii / Bilete gratuite -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Invitatii</h2>
                            <p class="text-sm text-gray-500">Genereaza bilete gratuite pentru invitati</p>
                        </div>
                        <a href="/organizator/invitations/<?= htmlspecialchars($eventData['id']) ?>" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">Gestioneaza</a>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4 mb-4">
                        <div class="p-4 bg-gray-50 rounded-xl text-center">
                            <p class="text-2xl font-bold text-gray-900">12</p>
                            <p class="text-sm text-gray-500">Invitatii emise</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl text-center">
                            <p class="text-2xl font-bold text-green-600">8</p>
                            <p class="text-sm text-gray-500">Trimise</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl text-center">
                            <p class="text-2xl font-bold text-amber-600">4</p>
                            <p class="text-sm text-gray-500">In asteptare</p>
                        </div>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <button onclick="document.getElementById('quickInviteModal').classList.remove('hidden')" class="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 text-gray-600 rounded-xl hover:border-indigo-400 hover:text-indigo-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Trimite invitatie rapida
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: MARKETING -->
        <div id="tab-marketing" class="tab-content">
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">SEO & Meta Tags</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Titlu SEO</label>
                                <input type="text" value="Coldplay Concert Bucuresti 2026 - Bilete | TICS" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                <p class="text-xs text-gray-500 mt-1">52/60 caractere</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Meta descriere</label>
                                <textarea rows="3" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none resize-none">Cumpara bilete pentru concertul Coldplay din Bucuresti, 14 februarie 2026. Music of the Spheres World Tour la Arena Nationala. Bilete de la 149 RON.</textarea>
                                <p class="text-xs text-gray-500 mt-1">148/160 caractere</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">URL personalizat</label>
                                <div class="flex">
                                    <span class="px-4 py-3 bg-gray-100 border border-r-0 border-gray-200 rounded-l-xl text-gray-500 text-sm">tics.ro/e/</span>
                                    <input type="text" id="customUrlInput" value="coldplay-bucuresti-2026" class="flex-1 px-4 py-3 border border-l-0 border-r-0 border-gray-200 outline-none" oninput="checkCustomUrl(this)">
                                    <button onclick="copyCustomUrl()" class="px-4 py-3 bg-gray-100 border border-l-0 border-gray-200 rounded-r-xl text-gray-600 hover:bg-gray-200 transition-colors" title="Copiaza link">
                                        <svg id="customUrlCopyIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Partajare Social Media</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Imagine social (1200x630px)</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 cursor-pointer">
                                    <p class="text-sm text-gray-500">Click pentru upload</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Text pentru partajare</label>
                                <textarea rows="3" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none resize-none">Coldplay vine la Bucuresti! Nu rata Music of the Spheres World Tour pe 14 februarie 2026. Ia-ti biletul!</textarea>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-4">
                            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook
                            </button>
                            <button class="flex items-center gap-2 px-4 py-2 bg-sky-500 text-white text-sm font-medium rounded-xl hover:bg-sky-600">Twitter</button>
                            <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-xl hover:bg-green-700">WhatsApp</button>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Campanii Email</h2>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-900">Anunt eveniment</p>
                                <p class="text-sm text-gray-500">Trimis la 1.234 abonati - 45% deschideri</p>
                            </div>
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Trimis</span>
                        </div>
                        <button class="mt-4 w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 text-gray-600 rounded-xl hover:border-indigo-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Creeaza campanie noua
                        </button>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 text-white">
                        <h3 class="font-bold mb-2">Performanta</h3>
                        <div class="space-y-3 mt-4">
                            <div class="flex justify-between"><span class="text-indigo-100">Vizite din social</span><span class="font-bold">3.245</span></div>
                            <div class="flex justify-between"><span class="text-indigo-100">Conversii</span><span class="font-bold">12%</span></div>
                            <div class="flex justify-between"><span class="text-indigo-100">Top sursa</span><span class="font-bold">Facebook</span></div>
                        </div>
                        <a href="/organizator/analytics/<?= htmlspecialchars($eventData['id']) ?>" class="mt-4 w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white/20 hover:bg-white/30 text-white text-sm font-medium rounded-xl transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Analytics detaliate
                        </a>
                    </div>

                    <!-- Widget Configurator -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900">Widget embed</h3>
                            <a href="/organizator/widget" target="_blank" class="text-sm text-indigo-600 hover:underline">Configurator avansat</a>
                        </div>

                        <!-- Quick Theme/Color Selection -->
                        <div class="space-y-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tema</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="setWidgetTheme('light')" id="widgetThemeLight" class="flex-1 flex items-center justify-center gap-2 p-2 border-2 border-indigo-500 bg-indigo-50 rounded-lg text-sm">
                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z"/></svg>
                                        Light
                                    </button>
                                    <button type="button" onclick="setWidgetTheme('dark')" id="widgetThemeDark" class="flex-1 flex items-center justify-center gap-2 p-2 border-2 border-gray-200 rounded-lg text-sm hover:border-gray-300">
                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/></svg>
                                        Dark
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Culoare</label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="setWidgetColor('indigo')" class="widget-color-btn w-7 h-7 rounded-lg bg-indigo-500 ring-2 ring-offset-2 ring-gray-900" data-color="indigo"></button>
                                    <button type="button" onclick="setWidgetColor('blue')" class="widget-color-btn w-7 h-7 rounded-lg bg-blue-500" data-color="blue"></button>
                                    <button type="button" onclick="setWidgetColor('green')" class="widget-color-btn w-7 h-7 rounded-lg bg-green-500" data-color="green"></button>
                                    <button type="button" onclick="setWidgetColor('purple')" class="widget-color-btn w-7 h-7 rounded-lg bg-purple-500" data-color="purple"></button>
                                    <button type="button" onclick="setWidgetColor('pink')" class="widget-color-btn w-7 h-7 rounded-lg bg-pink-500" data-color="pink"></button>
                                    <button type="button" onclick="setWidgetColor('orange')" class="widget-color-btn w-7 h-7 rounded-lg bg-orange-500" data-color="orange"></button>
                                </div>
                            </div>
                        </div>

                        <!-- Widget Preview Mini -->
                        <div class="bg-gray-100 rounded-xl p-4 mb-4">
                            <div id="miniWidgetPreview" class="bg-white rounded-xl border border-gray-200 p-3 max-w-xs mx-auto">
                                <div id="miniWidgetAccent" class="h-1 bg-indigo-600 rounded-full mb-3"></div>
                                <p class="font-semibold text-sm text-gray-900 mb-1"><?= htmlspecialchars($eventData['name']) ?></p>
                                <p class="text-xs text-gray-500 mb-2"><?= date('d M Y', strtotime($eventData['start_date'])) ?></p>
                                <div class="flex items-center justify-between">
                                    <span id="miniWidgetPrice" class="text-sm font-bold text-indigo-600">de la 149 RON</span>
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Disponibil</span>
                                </div>
                            </div>
                        </div>

                        <!-- Code Block -->
                        <div class="relative">
                            <pre id="widgetCodeBlock" class="bg-gray-900 text-green-400 p-3 rounded-lg text-xs overflow-x-auto">&lt;div class="tics-widget" data-event="<?= htmlspecialchars($eventData['id']) ?>" data-theme="light" data-color="indigo"&gt;&lt;/div&gt;
&lt;script src="https://tics.ro/widget.js"&gt;&lt;/script&gt;</pre>
                            <button onclick="copyWidgetCode()" class="absolute top-2 right-2 p-1.5 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">
                                <svg id="widgetCopyIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: SETARI -->
        <div id="tab-settings" class="tab-content">
            <div class="max-w-3xl space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Setari vanzari</h2>
                    <div class="space-y-4">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Inceput vanzari</label>
                                <input type="datetime-local" value="2025-12-01T10:00" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sfarsit vanzari</label>
                                <input type="datetime-local" value="2026-02-14T17:00" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Limita bilete/comanda</label>
                            <select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                <option>Fara limita</option>
                                <option>2 bilete</option>
                                <option>4 bilete</option>
                                <option selected>6 bilete</option>
                                <option>10 bilete</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Notificari</h2>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Raport zilnic</p>
                                <p class="text-xs text-gray-500">Sumar zilnic cu vanzarile</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                        <label class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Alerta sold out</p>
                                <p class="text-xs text-gray-500">Cand o categorie e sold out</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        </label>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Politica rambursare</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Permite rambursari</label>
                            <select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none">
                                <option>Nu permite</option>
                                <option selected>Pana la 7 zile inainte</option>
                                <option>Pana la 14 zile inainte</option>
                                <option>Oricand</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Termeni suplimentari</label>
                            <textarea rows="3" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none resize-none" placeholder="Termeni specifici pentru acest eveniment..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-red-200 p-6">
                    <h2 class="text-lg font-bold text-red-600 mb-2">Zona periculoasa</h2>
                    <p class="text-sm text-gray-500 mb-4">Aceste actiuni sunt ireversibile.</p>
                    <div class="flex flex-wrap gap-3">
                        <button class="px-4 py-2 border border-amber-300 text-amber-700 text-sm font-medium rounded-xl hover:bg-amber-50">Anuleaza evenimentul</button>
                        <button class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50">Sterge definitiv</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 5: PROMO (Servicii Promovare) -->
        <div id="tab-promo" class="tab-content">
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Servicii de Promovare</h2>
                        <p class="text-sm text-gray-500">Creste vizibilitatea evenimentului tau</p>
                    </div>
                </div>

                <!-- Services Grid -->
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Event Featuring -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">Promovare Eveniment</h3>
                                    <p class="text-sm text-gray-500 mb-4">Afiseaza evenimentul tau pe pagina principala, in categorii sau orase pentru vizibilitate maxima.</p>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2 py-1 bg-indigo-100 text-indigo-600 text-xs rounded-full">Pagina Principala</span>
                                        <span class="px-2 py-1 bg-indigo-100 text-indigo-600 text-xs rounded-full">Categorii</span>
                                        <span class="px-2 py-1 bg-indigo-100 text-indigo-600 text-xs rounded-full">Orase</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">De la <span class="text-indigo-600">49 RON</span> / zi</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <button onclick="openServiceModal('featuring')" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Cumpara Promovare
                            </button>
                        </div>
                    </div>

                    <!-- Email Marketing -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">Email Marketing</h3>
                                    <p class="text-sm text-gray-500 mb-4">Trimite emailuri targetate catre baza noastra de utilizatori sau doar catre clientii tai anteriori.</p>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2 py-1 bg-orange-100 text-orange-600 text-xs rounded-full">Baza Completa</span>
                                        <span class="px-2 py-1 bg-orange-100 text-orange-600 text-xs rounded-full">Audienta Filtrata</span>
                                        <span class="px-2 py-1 bg-orange-100 text-orange-600 text-xs rounded-full">Clientii Tai</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">De la <span class="text-orange-600">0.40 RON</span> / email</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <button onclick="openServiceModal('email')" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Cumpara Campanie Email
                            </button>
                        </div>
                    </div>

                    <!-- Ad Tracking -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">Tracking Campanii Ads</h3>
                                    <p class="text-sm text-gray-500 mb-4">Conecteaza campaniile tale Facebook, Google sau TikTok pentru a urmari conversiile si ROI-ul.</p>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">Facebook Ads</span>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">Google Ads</span>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-600 text-xs rounded-full">TikTok Ads</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">De la <span class="text-blue-600">99 RON</span> / luna</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <button onclick="openServiceModal('tracking')" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Activeaza Tracking
                            </button>
                        </div>
                    </div>

                    <!-- Ad Campaign Creation -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">Creare Campanii Ads</h3>
                                    <p class="text-sm text-gray-500 mb-4">Lasa echipa noastra sa creeze si sa gestioneze campanii publicitare profesionale pentru evenimentul tau.</p>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Strategie Completa</span>
                                        <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Design Creativ</span>
                                        <span class="px-2 py-1 bg-purple-100 text-purple-600 text-xs rounded-full">Management</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">De la <span class="text-purple-600">499 RON</span> / campanie</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <button onclick="openServiceModal('campaign')" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-xl hover:bg-purple-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Solicita Campanie
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Services -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900">Servicii Active</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-center py-8 text-gray-500">
                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            </div>
                            <p class="text-sm">Nu ai servicii active pentru acest eveniment</p>
                            <p class="text-xs text-gray-400 mt-1">Cumpara un serviciu pentru a-ti promova evenimentul</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Ticket Modal -->
    <div id="addTicketModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Adauga categorie bilete</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nume *</label>
                    <input type="text" placeholder="ex: Standard, VIP" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descriere</label>
                    <textarea rows="2" placeholder="Ce include..." class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pret (RON) *</label>
                        <input type="number" placeholder="149" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cantitate *</label>
                        <input type="number" placeholder="1000" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="document.getElementById('addTicketModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Adauga</button>
            </div>
        </div>
    </div>

    <!-- Quick Invite Modal -->
    <div id="quickInviteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Trimite invitatie rapida</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email invitat *</label>
                    <input type="email" placeholder="email@exemplu.com" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nume invitat</label>
                    <input type="text" placeholder="Ion Popescu" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tip bilet</label>
                    <select class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <?php foreach ($eventData['ticket_categories'] as $ticket): ?>
                        <?php if ($ticket['status'] !== 'soldout'): ?>
                        <option value="<?= htmlspecialchars($ticket['name']) ?>"><?= htmlspecialchars($ticket['name']) ?> (<?= number_format($ticket['price']) ?> RON)</option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Numar bilete</label>
                    <input type="number" value="1" min="1" max="10" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mesaj personal (optional)</label>
                    <textarea rows="2" placeholder="Mesaj care va aparea in email..." class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="document.getElementById('quickInviteModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="sendQuickInvite()" class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Trimite invitatie</button>
            </div>
        </div>
    </div>

    <!-- Add Promo Code Modal -->
    <div id="addPromoModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Creeaza cod promotional</h3>
                <button onclick="document.getElementById('addPromoModal').classList.add('hidden')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cod promotional *</label>
                    <div class="flex gap-2">
                        <input type="text" id="promoCodeInput" placeholder="Ex: SUMMER2026" class="flex-1 px-4 py-3 border border-gray-200 rounded-xl uppercase">
                        <button type="button" onclick="generatePromoCode()" class="px-4 py-3 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tip reducere *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="discountType" value="percentage" class="peer sr-only" checked>
                            <div class="p-4 border-2 border-gray-200 rounded-xl peer-checked:border-indigo-500 peer-checked:bg-indigo-50">
                                <p class="font-medium text-gray-900">Procent</p>
                                <p class="text-sm text-gray-500">Ex: 10% reducere</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="discountType" value="fixed" class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl peer-checked:border-indigo-500 peer-checked:bg-indigo-50">
                                <p class="font-medium text-gray-900">Suma fixa</p>
                                <p class="text-sm text-gray-500">Ex: 50 RON</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valoare reducere *</label>
                    <div class="relative">
                        <input type="number" id="discountValue" min="1" max="100" placeholder="10" class="w-full px-4 py-3 border border-gray-200 rounded-xl pr-12">
                        <span id="discountSuffix" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Aplicabil pentru</label>
                    <select class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <option value="all">Toate biletele</option>
                        <?php foreach ($eventData['ticket_categories'] as $ticket): ?>
                        <option value="<?= htmlspecialchars($ticket['name']) ?>">Doar <?= htmlspecialchars($ticket['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Limita utilizari</label>
                        <input type="number" placeholder="Nelimitat" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Per client</label>
                        <input type="number" placeholder="Nelimitat" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data inceput *</label>
                        <input type="date" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data sfarsit *</label>
                        <input type="date" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white p-6 border-t border-gray-100 flex gap-3">
                <button onclick="document.getElementById('addPromoModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="savePromoCode()" class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Salveaza</button>
            </div>
        </div>
    </div>

    <!-- Featuring Service Modal -->
    <div id="featuringServiceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Promovare Eveniment</h3>
                </div>
                <button onclick="closeServiceModal('featuring')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Locatie afisare *</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                            <div><span class="font-medium">Pagina Principala</span><span class="text-gray-500 text-sm ml-2">49 RON/zi</span></div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                            <div><span class="font-medium">Categoria: Concerte</span><span class="text-gray-500 text-sm ml-2">29 RON/zi</span></div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                            <div><span class="font-medium">Orasul: Bucuresti</span><span class="text-gray-500 text-sm ml-2">19 RON/zi</span></div>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data inceput *</label>
                        <input type="date" id="featuringStartDate" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data sfarsit *</label>
                        <input type="date" id="featuringEndDate" min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                </div>
                <div class="bg-indigo-50 rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total estimat:</span>
                        <span class="text-xl font-bold text-indigo-600">0 RON</span>
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white p-6 border-t border-gray-100 flex gap-3">
                <button onclick="closeServiceModal('featuring')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="submitServiceRequest('featuring')" class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Cumpara</button>
            </div>
        </div>
    </div>

    <!-- Email Marketing Service Modal -->
    <div id="emailServiceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Campanie Email</h3>
                </div>
                <button onclick="closeServiceModal('email')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <!-- Database Source -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sursa baza de date *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-orange-300 transition-colors" id="emailDbOwn">
                            <input type="radio" name="emailDatabase" value="own" class="w-4 h-4 text-orange-600" onchange="updateEmailAudienceFilters()">
                            <div>
                                <p class="font-medium text-gray-900">Clientii tai</p>
                                <p class="text-xs text-gray-500">~1.200 useri - 0.25 RON/email</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-orange-300 transition-colors" id="emailDbPlatform">
                            <input type="radio" name="emailDatabase" value="platform" class="w-4 h-4 text-orange-600" onchange="updateEmailAudienceFilters()">
                            <div>
                                <p class="font-medium text-gray-900">Baza platformei</p>
                                <p class="text-xs text-gray-500">~250.000 useri - 0.40 RON/email</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Demographic Filters -->
                <div id="emailFiltersSection" class="hidden space-y-4 p-4 bg-gray-50 rounded-xl">
                    <h4 class="font-medium text-gray-900 flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtre demografice
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Varsta minima</label>
                            <input type="number" id="emailAgeMin" placeholder="18" min="18" max="100" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Varsta maxima</label>
                            <input type="number" id="emailAgeMax" placeholder="65" min="18" max="100" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Sex</label>
                            <select id="emailGender" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                                <option value="">Toti</option>
                                <option value="M">Barbati</option>
                                <option value="F">Femei</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Oras</label>
                            <select id="emailCity" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                                <option value="">Toate orasele</option>
                                <option value="bucuresti">Bucuresti</option>
                                <option value="cluj">Cluj-Napoca</option>
                                <option value="timisoara">Timisoara</option>
                                <option value="iasi">Iasi</option>
                                <option value="constanta">Constanta</option>
                                <option value="brasov">Brasov</option>
                                <option value="sibiu">Sibiu</option>
                                <option value="craiova">Craiova</option>
                            </select>
                        </div>
                    </div>

                    <h4 class="font-medium text-gray-900 flex items-center gap-2 pt-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        Interese
                    </h4>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Gen evenimente</label>
                        <select id="emailEventGenre" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                            <option value="">Toate genurile</option>
                            <option value="concert">Concerte</option>
                            <option value="festival">Festivaluri</option>
                            <option value="teatru">Teatru</option>
                            <option value="sport">Sport</option>
                            <option value="standup">Stand-up Comedy</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Genuri muzicale</label>
                        <div class="flex flex-wrap gap-2" id="emailMusicGenres">
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full cursor-pointer hover:border-orange-300 text-sm">
                                <input type="checkbox" class="w-3.5 h-3.5 text-orange-600 rounded" onchange="updateEmailEstimate()"> Rock
                            </label>
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full cursor-pointer hover:border-orange-300 text-sm">
                                <input type="checkbox" class="w-3.5 h-3.5 text-orange-600 rounded" onchange="updateEmailEstimate()"> Pop
                            </label>
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full cursor-pointer hover:border-orange-300 text-sm">
                                <input type="checkbox" class="w-3.5 h-3.5 text-orange-600 rounded" onchange="updateEmailEstimate()"> Electronic
                            </label>
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full cursor-pointer hover:border-orange-300 text-sm">
                                <input type="checkbox" class="w-3.5 h-3.5 text-orange-600 rounded" onchange="updateEmailEstimate()"> Hip-Hop
                            </label>
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full cursor-pointer hover:border-orange-300 text-sm">
                                <input type="checkbox" class="w-3.5 h-3.5 text-orange-600 rounded" onchange="updateEmailEstimate()"> Jazz
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Artisti preferati</label>
                        <input type="text" id="emailFavoriteArtists" placeholder="Ex: Coldplay, Ed Sheeran, Dua Lipa" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="updateEmailEstimate()">
                        <p class="text-xs text-gray-400 mt-1">Separa artistii prin virgula</p>
                    </div>
                    <div class="bg-orange-100 rounded-lg p-3 flex items-center justify-between">
                        <span class="text-sm text-orange-800">Audienta estimata:</span>
                        <span class="font-bold text-orange-600" id="emailAudienceEstimate">~50.000 useri</span>
                    </div>
                </div>

                <!-- Email Content -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subiect email *</label>
                    <input type="text" id="emailSubject" placeholder="Ex: Nu rata concertul Coldplay!" class="w-full px-4 py-3 border border-gray-200 rounded-xl" oninput="updateEmailPreview()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mesaj promotional</label>
                    <textarea id="emailBody" rows="4" placeholder="Scrie mesajul care va fi trimis..." class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none" oninput="updateEmailPreview()"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data trimitere *</label>
                    <input type="datetime-local" id="emailSendDate" min="<?= date('Y-m-d\TH:i') ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>

                <!-- Email Preview -->
                <div>
                    <button type="button" onclick="toggleEmailPreview()" class="flex items-center gap-2 text-sm font-medium text-orange-600 hover:text-orange-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span id="emailPreviewToggleText">Arata preview email</span>
                    </button>
                    <div id="emailPreviewContainer" class="hidden mt-3">
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-100 px-4 py-2 border-b border-gray-200">
                                <p class="text-xs text-gray-500">Preview email</p>
                            </div>
                            <div class="p-4 bg-white">
                                <div class="mb-3 pb-3 border-b border-gray-100">
                                    <p class="text-xs text-gray-400">De la: TICS Events &lt;noreply@tics.ro&gt;</p>
                                    <p class="text-xs text-gray-400">Catre: <span class="text-gray-600">utilizator@email.com</span></p>
                                    <p class="text-sm font-medium text-gray-900 mt-1" id="emailPreviewSubject">Subiect email...</p>
                                </div>
                                <div class="prose prose-sm max-w-none text-gray-600" id="emailPreviewBody">
                                    <p class="text-gray-400 italic">Mesajul tau va aparea aici...</p>
                                </div>
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <a href="#" class="inline-block px-6 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg">Cumpara bilete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-orange-50 rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total estimat:</span>
                        <span class="text-xl font-bold text-orange-600" id="emailTotalCost">0 RON</span>
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white p-6 border-t border-gray-100 flex gap-3">
                <button onclick="closeServiceModal('email')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="submitServiceRequest('email')" class="flex-1 py-3 bg-orange-500 text-white font-medium rounded-xl hover:bg-orange-600">Programeaza</button>
            </div>
        </div>
    </div>

    <!-- Ad Tracking Service Modal -->
    <div id="trackingServiceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Tracking Campanii Ads</h3>
                </div>
                <button onclick="closeServiceModal('tracking')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Platforme de tracking *</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-blue-600">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                <span class="font-medium">Facebook Pixel</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-blue-600">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/></svg>
                                <span class="font-medium">Google Ads</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-blue-600">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                                <span class="font-medium">TikTok Pixel</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Durata abonament *</label>
                    <select class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <option value="1">1 luna - 99 RON</option>
                        <option value="3">3 luni - 249 RON (economisesti 48 RON)</option>
                        <option value="6">6 luni - 449 RON (economisesti 145 RON)</option>
                    </select>
                </div>
                <div class="bg-blue-50 rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total:</span>
                        <span class="text-xl font-bold text-blue-600">99 RON</span>
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white p-6 border-t border-gray-100 flex gap-3">
                <button onclick="closeServiceModal('tracking')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="submitServiceRequest('tracking')" class="flex-1 py-3 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700">Activeaza</button>
            </div>
        </div>
    </div>

    <!-- Campaign Creation Service Modal -->
    <div id="campaignServiceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-gray-100 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Creare Campanii Ads</h3>
                </div>
                <button onclick="closeServiceModal('campaign')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                    <p class="text-sm text-purple-800">Echipa noastra de marketing va crea si gestiona campanii publicitare profesionale pentru evenimentul tau.</p>
                </div>

                <!-- Platform Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Platforme dorite *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300 transition-colors" id="platformFb">
                            <input type="checkbox" name="adPlatforms" value="facebook" class="w-4 h-4 text-purple-600 rounded" onchange="updateCampaignPrice()">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                <span class="text-sm font-medium">Facebook</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300 transition-colors" id="platformIg">
                            <input type="checkbox" name="adPlatforms" value="instagram" class="w-4 h-4 text-purple-600 rounded" onchange="updateCampaignPrice()">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="url(#instagram-gradient)"><defs><linearGradient id="instagram-gradient" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#FFDC80"/><stop offset="25%" stop-color="#F77737"/><stop offset="50%" stop-color="#F56040"/><stop offset="75%" stop-color="#C13584"/><stop offset="100%" stop-color="#833AB4"/></linearGradient></defs><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                <span class="text-sm font-medium">Instagram</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300 transition-colors" id="platformGoogle">
                            <input type="checkbox" name="adPlatforms" value="google" class="w-4 h-4 text-purple-600 rounded" onchange="updateCampaignPrice()">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                <span class="text-sm font-medium">Google Ads</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300 transition-colors" id="platformTiktok">
                            <input type="checkbox" name="adPlatforms" value="tiktok" class="w-4 h-4 text-purple-600 rounded" onchange="updateCampaignPrice()">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                                <span class="text-sm font-medium">TikTok</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Image Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Imagini campanie</label>
                    <div id="campaignDropzone" class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-purple-400 transition-colors cursor-pointer" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)" onclick="document.getElementById('campaignImageInput').click()">
                        <input type="file" id="campaignImageInput" multiple accept="image/*" class="hidden" onchange="handleFileSelect(event)">
                        <svg class="w-10 h-10 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm text-gray-600 mb-1">Trage imaginile aici sau click pentru upload</p>
                        <p class="text-xs text-gray-400">PNG, JPG pana la 10MB. Recomandat: 1200x628px</p>
                    </div>
                    <div id="campaignImagePreview" class="grid grid-cols-3 gap-2 mt-3 hidden"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pachet dorit *</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300">
                            <input type="radio" name="campaignPackage" value="basic" class="mt-1 w-5 h-5 border-gray-300 text-purple-600" onchange="updateCampaignPrice()">
                            <div>
                                <p class="font-medium text-gray-900">Basic - 499 RON</p>
                                <p class="text-sm text-gray-500">1 platforma, design creativ, setup campanie</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300">
                            <input type="radio" name="campaignPackage" value="pro" class="mt-1 w-5 h-5 border-gray-300 text-purple-600" onchange="updateCampaignPrice()">
                            <div>
                                <p class="font-medium text-gray-900">Pro - 899 RON</p>
                                <p class="text-sm text-gray-500">2 platforme, A/B testing, optimizare continua</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300">
                            <input type="radio" name="campaignPackage" value="enterprise" class="mt-1 w-5 h-5 border-gray-300 text-purple-600" onchange="updateCampaignPrice()">
                            <div>
                                <p class="font-medium text-gray-900">Enterprise - 1499 RON</p>
                                <p class="text-sm text-gray-500">Toate platformele, strategie completa, manager dedicat</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Buget publicitar estimat (RON)</label>
                    <input type="number" id="campaignBudget" placeholder="Ex: 2000" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <p class="text-xs text-gray-500 mt-1">Bugetul pentru platformele de ads (nu include taxa de management)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Note sau cerinte speciale</label>
                    <textarea rows="3" placeholder="Descrie obiectivele campaniei..." class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none"></textarea>
                </div>
                <div class="bg-purple-50 rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total estimat:</span>
                        <span class="text-xl font-bold text-purple-600" id="campaignTotalPrice">0 RON</span>
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 bg-white p-6 border-t border-gray-100 flex gap-3">
                <button onclick="closeServiceModal('campaign')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button onclick="submitServiceRequest('campaign')" class="flex-1 py-3 bg-purple-600 text-white font-medium rounded-xl hover:bg-purple-700">Solicita oferta</button>
            </div>
        </div>
    </div>

    <!-- Postponed Modal -->
    <div id="postponedModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Amana evenimentul</h3>
                </div>
            </div>
            <form id="postponedForm" onsubmit="savePostponed(event)" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Noua data a evenimentului *</label>
                    <input type="date" name="postponed_date" id="postponedDate" min="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Ora start</label>
                        <input type="time" name="postponed_start_time" id="postponedStartTime" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Ora deschidere</label>
                        <input type="time" name="postponed_door_time" id="postponedDoorTime" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Ora sfarsit</label>
                        <input type="time" name="postponed_end_time" id="postponedEndTime" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivul amanarii</label>
                    <textarea name="postponed_reason" id="postponedReason" rows="3" placeholder="Ex: Din motive tehnice, evenimentul a fost amanat..." class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none"></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closePostponedModal()" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                    <button type="submit" class="flex-1 py-3 bg-orange-500 text-white font-medium rounded-xl hover:bg-orange-600">Marcheaza ca amanat</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancelled Modal -->
    <div id="cancelledModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Anuleaza evenimentul</h3>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <p class="text-sm font-medium text-red-800">Atentie!</p>
                            <p class="text-sm text-red-700 mt-1">Anularea evenimentului va notifica toti participantii si nu poate fi anulata.</p>
                        </div>
                    </div>
                </div>
                <form id="cancelledForm" onsubmit="saveCancelled(event)">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivul anularii *</label>
                        <textarea name="cancel_reason" id="cancelReason" rows="3" placeholder="Ex: Din cauza conditiilor meteo, evenimentul a fost anulat..." required class="w-full px-4 py-3 border border-gray-200 rounded-xl resize-none"></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeCancelledModal()" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Inapoi</button>
                        <button type="submit" class="flex-1 py-3 bg-red-500 text-white font-medium rounded-xl hover:bg-red-600">Confirma anularea</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                b.classList.add('text-gray-500');
            });
            document.getElementById('tab-' + tabName).classList.add('active');
            const activeBtn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
            activeBtn.classList.add('active');
            activeBtn.classList.remove('text-gray-500');
        }

        function saveChanges() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se salveaza...';
            setTimeout(() => {
                btn.innerHTML = '✓ Salvat!';
                btn.classList.replace('bg-indigo-600', 'bg-green-600');
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.replace('bg-green-600', 'bg-indigo-600');
                }, 2000);
            }, 1000);
        }

        // ============ WIDGET CONFIGURATOR ============
        const widgetConfig = {
            theme: 'light',
            color: 'indigo',
            eventId: '<?= htmlspecialchars($eventData['id']) ?>'
        };

        const colorHexMap = {
            indigo: '#6366f1',
            blue: '#3b82f6',
            green: '#22c55e',
            purple: '#8b5cf6',
            pink: '#ec4899',
            orange: '#f97316'
        };

        function setWidgetTheme(theme) {
            widgetConfig.theme = theme;
            document.getElementById('widgetThemeLight').className = 'flex-1 flex items-center justify-center gap-2 p-2 border-2 rounded-lg text-sm ' +
                (theme === 'light' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');
            document.getElementById('widgetThemeDark').className = 'flex-1 flex items-center justify-center gap-2 p-2 border-2 rounded-lg text-sm ' +
                (theme === 'dark' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');

            const preview = document.getElementById('miniWidgetPreview');
            if (theme === 'dark') {
                preview.className = 'bg-gray-900 rounded-xl border border-gray-700 p-3 max-w-xs mx-auto';
                preview.querySelector('p:first-of-type').className = 'font-semibold text-sm text-white mb-1';
                preview.querySelector('p:nth-of-type(2)').className = 'text-xs text-gray-400 mb-2';
            } else {
                preview.className = 'bg-white rounded-xl border border-gray-200 p-3 max-w-xs mx-auto';
                preview.querySelector('p:first-of-type').className = 'font-semibold text-sm text-gray-900 mb-1';
                preview.querySelector('p:nth-of-type(2)').className = 'text-xs text-gray-500 mb-2';
            }
            updateWidgetCode();
        }

        function setWidgetColor(color) {
            widgetConfig.color = color;
            document.querySelectorAll('.widget-color-btn').forEach(btn => {
                btn.classList.remove('ring-2', 'ring-offset-2', 'ring-gray-900');
            });
            document.querySelector(`.widget-color-btn[data-color="${color}"]`).classList.add('ring-2', 'ring-offset-2', 'ring-gray-900');

            const hex = colorHexMap[color];
            document.getElementById('miniWidgetAccent').style.backgroundColor = hex;
            document.getElementById('miniWidgetPrice').style.color = hex;
            updateWidgetCode();
        }

        function updateWidgetCode() {
            const code = `<div class="tics-widget" data-event="${widgetConfig.eventId}" data-theme="${widgetConfig.theme}" data-color="${widgetConfig.color}"></div>
<script src="https://tics.ro/widget.js"><\/script>`;
            document.getElementById('widgetCodeBlock').textContent = code;
        }

        function copyWidgetCode() {
            const code = `<div class="tics-widget" data-event="${widgetConfig.eventId}" data-theme="${widgetConfig.theme}" data-color="${widgetConfig.color}"></div>\n<script src="https://tics.ro/widget.js"><\/script>`;
            navigator.clipboard.writeText(code).then(() => {
                const icon = document.getElementById('widgetCopyIcon');
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
                setTimeout(() => {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                }, 2000);
            });
        }

        // ============ CUSTOM URL ============
        let urlCheckTimeout;
        function checkCustomUrl(input) {
            clearTimeout(urlCheckTimeout);
            const slug = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
            input.value = slug;

            const statusEl = input.parentElement.querySelector('.url-status');
            if (!statusEl) {
                const status = document.createElement('span');
                status.className = 'url-status absolute right-3 top-1/2 -translate-y-1/2';
                input.parentElement.style.position = 'relative';
                input.parentElement.appendChild(status);
            }

            if (slug.length < 3) return;

            urlCheckTimeout = setTimeout(() => {
                // Simulate URL check
                const available = !['coldplay', 'untold', 'neversea'].includes(slug);
                const statusEl = input.parentElement.querySelector('.url-status');
                if (available) {
                    statusEl.innerHTML = '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                } else {
                    statusEl.innerHTML = '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                }
            }, 500);
        }

        // ============ PROMO CODES ============
        function generatePromoCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 8; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('promoCodeInput').value = code;
        }

        function copyPromoCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                // Show brief notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Codul a fost copiat!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            });
        }

        function savePromoCode() {
            const btn = event.target;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se salveaza...';
            setTimeout(() => {
                document.getElementById('addPromoModal').classList.add('hidden');
                btn.innerHTML = 'Salveaza';
                // Show success notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Codul a fost salvat!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            }, 1000);
        }

        // Discount type toggle
        document.querySelectorAll('input[name="discountType"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const suffix = document.getElementById('discountSuffix');
                const input = document.getElementById('discountValue');
                if (this.value === 'percentage') {
                    suffix.textContent = '%';
                    input.max = 100;
                } else {
                    suffix.textContent = 'RON';
                    input.max = 10000;
                }
            });
        });

        // ============ INVITATIONS ============
        function sendQuickInvite() {
            const btn = event.target;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se trimite...';
            setTimeout(() => {
                document.getElementById('quickInviteModal').classList.add('hidden');
                btn.innerHTML = 'Trimite invitatie';
                // Show success notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Invitatia a fost trimisa!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            }, 1000);
        }

        // ============ TICKET STOCK ============
        function updateTicketStock(index, value) {
            // Just update the input value, actual save happens on button click
            console.log(`Ticket ${index} stock updated to ${value}`);
        }

        function toggleSoldOut(index, isSoldOut) {
            const stockInput = document.getElementById(`ticketStock_${index}`);
            if (stockInput) {
                stockInput.disabled = isSoldOut;
                stockInput.parentElement.parentElement.style.opacity = isSoldOut ? '0.5' : '1';
            }
        }

        function saveTicketStock(index) {
            const btn = event.target;
            const originalText = btn.textContent;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';

            setTimeout(() => {
                btn.textContent = originalText;
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Modificarile au fost salvate!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            }, 800);
        }

        // ============ CUSTOM URL COPY ============
        function copyCustomUrl() {
            const slug = document.getElementById('customUrlInput').value;
            const fullUrl = `https://tics.ro/e/${slug}`;
            navigator.clipboard.writeText(fullUrl).then(() => {
                const icon = document.getElementById('customUrlCopyIcon');
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
                setTimeout(() => {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                }, 2000);

                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Link copiat!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            });
        }

        // ============ PROMOTION SERVICES ============
        function openServiceModal(serviceType) {
            const modal = document.getElementById(`${serviceType}ServiceModal`);
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closeServiceModal(serviceType) {
            const modal = document.getElementById(`${serviceType}ServiceModal`);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function submitServiceRequest(serviceType) {
            const btn = event.target;
            const originalText = btn.textContent;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se proceseaza...';

            setTimeout(() => {
                closeServiceModal(serviceType);
                btn.innerHTML = originalText;

                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-xl text-sm z-50';
                notification.textContent = 'Solicitarea a fost trimisa! Vei fi contactat in curand.';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            }, 1000);
        }

        // ============ EMAIL CAMPAIGN FUNCTIONS ============
        function updateEmailAudienceFilters() {
            const platformDb = document.querySelector('input[name="emailDatabase"][value="platform"]');
            const filtersSection = document.getElementById('emailFiltersSection');

            if (platformDb && platformDb.checked) {
                filtersSection.classList.remove('hidden');
            } else {
                filtersSection.classList.add('hidden');
            }
            updateEmailEstimate();
        }

        function updateEmailEstimate() {
            const platformDb = document.querySelector('input[name="emailDatabase"][value="platform"]');
            const ownDb = document.querySelector('input[name="emailDatabase"][value="own"]');

            let baseCount = 0;
            let pricePerEmail = 0;

            if (platformDb && platformDb.checked) {
                baseCount = 250000;
                pricePerEmail = 0.40;

                // Apply filters
                const ageMin = parseInt(document.getElementById('emailAgeMin')?.value) || 0;
                const ageMax = parseInt(document.getElementById('emailAgeMax')?.value) || 100;
                const gender = document.getElementById('emailGender')?.value || '';
                const city = document.getElementById('emailCity')?.value || '';

                if (ageMin > 18 || ageMax < 65) baseCount *= 0.6;
                if (gender) baseCount *= 0.5;
                if (city) baseCount *= 0.15;

                // Check music genres
                const checkedGenres = document.querySelectorAll('#emailMusicGenres input:checked').length;
                if (checkedGenres > 0) baseCount *= (0.3 + checkedGenres * 0.1);

                pricePerEmail = 0.35; // Filtered price
            } else if (ownDb && ownDb.checked) {
                baseCount = 1200;
                pricePerEmail = 0.25;
            }

            const estimateEl = document.getElementById('emailAudienceEstimate');
            if (estimateEl) {
                estimateEl.textContent = `~${Math.round(baseCount).toLocaleString()} useri`;
            }

            const totalCost = Math.round(baseCount * pricePerEmail);
            const costEl = document.getElementById('emailTotalCost');
            if (costEl) {
                costEl.textContent = `${totalCost.toLocaleString()} RON`;
            }
        }

        function toggleEmailPreview() {
            const container = document.getElementById('emailPreviewContainer');
            const toggleText = document.getElementById('emailPreviewToggleText');

            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                toggleText.textContent = 'Ascunde preview email';
            } else {
                container.classList.add('hidden');
                toggleText.textContent = 'Arata preview email';
            }
        }

        function updateEmailPreview() {
            const subject = document.getElementById('emailSubject')?.value || 'Subiect email...';
            const body = document.getElementById('emailBody')?.value || '';

            document.getElementById('emailPreviewSubject').textContent = subject;

            const bodyEl = document.getElementById('emailPreviewBody');
            if (body) {
                bodyEl.innerHTML = body.split('\n').map(p => `<p>${p}</p>`).join('');
            } else {
                bodyEl.innerHTML = '<p class="text-gray-400 italic">Mesajul tau va aparea aici...</p>';
            }
        }

        // ============ CAMPAIGN ADS FUNCTIONS ============
        let campaignImages = [];

        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('campaignDropzone').classList.add('border-purple-400', 'bg-purple-50');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('campaignDropzone').classList.remove('border-purple-400', 'bg-purple-50');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('campaignDropzone').classList.remove('border-purple-400', 'bg-purple-50');

            const files = e.dataTransfer.files;
            handleFiles(files);
        }

        function handleFileSelect(e) {
            const files = e.target.files;
            handleFiles(files);
        }

        function handleFiles(files) {
            const previewContainer = document.getElementById('campaignImagePreview');
            previewContainer.classList.remove('hidden');

            Array.from(files).forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;
                if (file.size > 10 * 1024 * 1024) {
                    alert('Imaginea depaseste limita de 10MB');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    campaignImages.push({ name: file.name, data: e.target.result });
                    renderImagePreviews();
                };
                reader.readAsDataURL(file);
            });
        }

        function renderImagePreviews() {
            const previewContainer = document.getElementById('campaignImagePreview');
            previewContainer.innerHTML = campaignImages.map((img, idx) => `
                <div class="relative group">
                    <img src="${img.data}" alt="${img.name}" class="w-full h-24 object-cover rounded-lg">
                    <button type="button" onclick="removeCampaignImage(${idx})" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            `).join('');

            if (campaignImages.length === 0) {
                previewContainer.classList.add('hidden');
            }
        }

        function removeCampaignImage(index) {
            campaignImages.splice(index, 1);
            renderImagePreviews();
        }

        function updateCampaignPrice() {
            const packageRadios = document.querySelectorAll('input[name="campaignPackage"]');
            let packagePrice = 0;

            packageRadios.forEach(radio => {
                if (radio.checked) {
                    if (radio.value === 'basic') packagePrice = 499;
                    else if (radio.value === 'pro') packagePrice = 899;
                    else if (radio.value === 'enterprise') packagePrice = 1499;
                }
            });

            const priceEl = document.getElementById('campaignTotalPrice');
            if (priceEl) {
                priceEl.textContent = `${packagePrice.toLocaleString()} RON`;
            }
        }

        // ============ EVENT STATUS FUNCTIONS ============
        let eventIsSoldOut = false;
        let eventIsPostponed = false;
        let eventIsCancelled = false;

        function toggleSoldOut() {
            eventIsSoldOut = !eventIsSoldOut;
            const btn = document.getElementById('btn-sold-out');

            if (eventIsSoldOut) {
                btn.classList.add('bg-amber-50', 'border-amber-300');
                btn.querySelector('p.text-sm').textContent = 'Eveniment marcat ca sold out';
                showNotification('Evenimentul a fost marcat ca sold out!', 'warning');
            } else {
                btn.classList.remove('bg-amber-50', 'border-amber-300');
                btn.querySelector('p.text-sm').textContent = 'Marcheaza ca sold out';
                showNotification('Sold out a fost dezactivat.', 'info');
            }
        }

        function showPostponedModal() {
            const modal = document.getElementById('postponedModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closePostponedModal() {
            const modal = document.getElementById('postponedModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function savePostponed(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se salveaza...';

            setTimeout(() => {
                eventIsPostponed = true;
                closePostponedModal();
                btn.textContent = originalText;

                // Update header status badge
                const statusBadge = document.querySelector('.bg-green-100.text-green-700');
                if (statusBadge) {
                    statusBadge.className = 'px-3 py-1 bg-orange-100 text-orange-700 text-sm font-medium rounded-full';
                    statusBadge.textContent = 'Amanat';
                }

                // Update button appearance
                const btn2 = document.getElementById('btn-postponed');
                btn2.classList.add('bg-orange-50', 'border-orange-300');
                btn2.querySelector('p.text-sm').textContent = 'Eveniment amanat';

                showNotification('Evenimentul a fost marcat ca amanat! Participantii vor fi notificati.', 'warning');
            }, 1000);
        }

        function showCancelledModal() {
            const modal = document.getElementById('cancelledModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closeCancelledModal() {
            const modal = document.getElementById('cancelledModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function saveCancelled(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Se proceseaza...';

            setTimeout(() => {
                eventIsCancelled = true;
                closeCancelledModal();
                btn.textContent = originalText;

                // Update header status badge
                const statusBadge = document.querySelector('.bg-green-100.text-green-700, .bg-orange-100.text-orange-700');
                if (statusBadge) {
                    statusBadge.className = 'px-3 py-1 bg-red-100 text-red-700 text-sm font-medium rounded-full';
                    statusBadge.textContent = 'Anulat';
                }

                // Update button appearance
                const btn2 = document.getElementById('btn-cancelled');
                btn2.classList.add('bg-red-50', 'border-red-300');
                btn2.querySelector('p.text-sm').textContent = 'Eveniment anulat';

                showNotification('Evenimentul a fost anulat. Toti participantii vor fi notificati.', 'error');
            }, 1500);
        }

        function showNotification(message, type = 'success') {
            const colors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                warning: 'bg-orange-500',
                info: 'bg-gray-900'
            };

            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-xl text-sm z-50`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // ============ MODAL HANDLING ============
        // Close modals on backdrop click
        ['addTicketModal', 'quickInviteModal', 'addPromoModal', 'featuringServiceModal', 'emailServiceModal', 'trackingServiceModal', 'campaignServiceModal', 'postponedModal', 'cancelledModal'].forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.add('hidden');
                    }
                });
            }
        });

        // Add URL check listener
        document.addEventListener('DOMContentLoaded', function() {
            const customUrlInput = document.querySelector('input[value="coldplay-bucuresti-2026"]');
            if (customUrlInput) {
                customUrlInput.addEventListener('input', function() {
                    checkCustomUrl(this);
                });
            }
        });
    </script>
</body>
</html>
