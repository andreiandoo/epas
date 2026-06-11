<?php
/**
 * TICS Organizer Documents - Fiscal Documents Management
 * Generate and download fiscal documents for events
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Demo events with documents
$events = [
    [
        'id' => 'coldplay-2026',
        'name' => 'Coldplay: Music of the Spheres',
        'date' => '2026-02-14',
        'venue' => 'Arena Nationala, Bucuresti',
        'status' => 'on_sale',
        'status_label' => 'In vanzare',
        'has_aviz' => true,
        'has_impozite' => false,
        'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=100&h=100&fit=crop',
    ],
    [
        'id' => 'untold-2025',
        'name' => 'UNTOLD Festival 2025',
        'date' => '2025-08-07',
        'venue' => 'Cluj Arena, Cluj-Napoca',
        'status' => 'ended',
        'status_label' => 'Incheiat',
        'has_aviz' => true,
        'has_impozite' => true,
        'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=100&h=100&fit=crop',
    ],
    [
        'id' => 'comedy-show',
        'name' => 'Stand-up Comedy Night',
        'date' => '2025-12-20',
        'venue' => 'Sala Palatului, Bucuresti',
        'status' => 'ended',
        'status_label' => 'Incheiat',
        'has_aviz' => true,
        'has_impozite' => true,
        'image' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=100&h=100&fit=crop',
    ],
];

// Demo document history
$documentHistory = [
    ['id' => 1, 'event' => 'Coldplay: Music of the Spheres', 'type' => 'Cerere Avizare', 'date' => '2025-01-15', 'status' => 'generated'],
    ['id' => 2, 'event' => 'UNTOLD Festival 2025', 'type' => 'Cerere Avizare', 'date' => '2025-06-20', 'status' => 'generated'],
    ['id' => 3, 'event' => 'UNTOLD Festival 2025', 'type' => 'Declaratie Impozite', 'date' => '2025-08-15', 'status' => 'generated'],
    ['id' => 4, 'event' => 'Stand-up Comedy Night', 'type' => 'Cerere Avizare', 'date' => '2025-11-25', 'status' => 'generated'],
    ['id' => 5, 'event' => 'Stand-up Comedy Night', 'type' => 'Declaratie Impozite', 'date' => '2025-12-22', 'status' => 'generated'],
];

// Current page for sidebar
$currentPage = 'documents';

// Page config for head
$pageTitle = 'Documente';
$pageDescription = 'Genereaza si descarca documentele fiscale';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Documente</h1>
                            <p class="text-sm text-gray-500">Genereaza si descarca documentele fiscale pentru evenimentele tale</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Event Selector -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selecteaza evenimentul</label>
                    <div class="relative max-w-lg">
                        <select id="eventSelector" onchange="selectEvent(this.value)" class="w-full px-4 py-3 pr-10 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 appearance-none bg-white">
                            <option value="">-- Alege un eveniment --</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?= htmlspecialchars($event['id']) ?>"><?= htmlspecialchars($event['name']) ?> - <?= date('d M Y', strtotime($event['date'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>

                <!-- Event Detail + Document Generation (hidden until event selected) -->
                <div id="eventDetailSection" class="hidden mb-6">
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                        <!-- Event Info Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-start gap-4">
                                <img id="eventImage" src="" alt="" class="w-16 h-16 rounded-xl object-cover">
                                <div class="flex-1">
                                    <h2 id="eventName" class="text-lg font-bold text-gray-900"></h2>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mt-1">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span id="eventVenue"></span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span id="eventDate"></span>
                                        </span>
                                        <span id="eventStatusBadge"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Document Generation Buttons -->
                        <div class="p-6">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Documente disponibile</h3>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- Cerere Avizare -->
                                <div class="border border-gray-200 rounded-xl p-5 hover:border-indigo-200 hover:bg-indigo-50/30 transition-colors">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Cerere Avizare</h4>
                                            <p class="text-xs text-gray-500">Document pentru avizarea evenimentului</p>
                                        </div>
                                    </div>
                                    <div id="avizActions" class="flex flex-wrap items-center gap-2"></div>
                                </div>

                                <!-- Declaratie Impozite -->
                                <div class="border border-gray-200 rounded-xl p-5 hover:border-indigo-200 hover:bg-indigo-50/30 transition-colors">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Declaratie Impozite</h4>
                                            <p class="text-xs text-gray-500">Disponibil dupa terminarea evenimentului</p>
                                        </div>
                                    </div>
                                    <div id="impoziteActions" class="flex flex-wrap items-center gap-2"></div>
                                </div>

                                <!-- Raport Vanzari -->
                                <div class="border border-gray-200 rounded-xl p-5 hover:border-indigo-200 hover:bg-indigo-50/30 transition-colors">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Raport Vanzari</h4>
                                            <p class="text-xs text-gray-500">Raport detaliat in format PDF</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button onclick="generateReport()" class="flex items-center gap-2 px-4 py-2 bg-purple-100 text-purple-700 text-sm font-medium rounded-lg hover:bg-purple-200 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Genereaza PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents History -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">Istoric documente</h2>
                        <p class="text-sm text-gray-500 mt-1">Toate documentele generate pentru evenimentele tale</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Eveniment</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Tip document</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Data generarii</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($documentHistory as $doc): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($doc['event']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <?php if ($doc['type'] === 'Cerere Avizare'): ?>
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <?php else: ?>
                                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                            <?php endif; ?>
                                            <span class="text-sm text-gray-600"><?= htmlspecialchars($doc['type']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-500"><?= date('d M Y', strtotime($doc['date'])) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Generat
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="downloadDocument(<?= $doc['id'] ?>)" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Descarca">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </button>
                                            <button onclick="regenerateDocument(<?= $doc['id'] ?>)" class="p-2 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Regenereaza">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Events data
        const eventsData = <?= json_encode(array_combine(array_column($events, 'id'), $events)) ?>;

        function selectEvent(eventId) {
            const detailSection = document.getElementById('eventDetailSection');

            if (!eventId || !eventsData[eventId]) {
                detailSection.classList.add('hidden');
                return;
            }

            const event = eventsData[eventId];

            // Populate event info
            document.getElementById('eventImage').src = event.image;
            document.getElementById('eventName').textContent = event.name;
            document.getElementById('eventVenue').textContent = event.venue;
            document.getElementById('eventDate').textContent = formatDate(event.date);

            // Status badge
            const statusColors = {
                on_sale: { bg: 'bg-green-100', text: 'text-green-700' },
                ended: { bg: 'bg-gray-100', text: 'text-gray-700' },
                draft: { bg: 'bg-amber-100', text: 'text-amber-700' },
            };
            const statusStyle = statusColors[event.status] || statusColors.draft;
            document.getElementById('eventStatusBadge').innerHTML = `<span class="px-2.5 py-1 ${statusStyle.bg} ${statusStyle.text} text-xs font-medium rounded-full">${event.status_label}</span>`;

            // Render document actions
            renderAvizActions(event);
            renderImpoziteActions(event);

            detailSection.classList.remove('hidden');
        }

        function renderAvizActions(event) {
            const container = document.getElementById('avizActions');
            if (event.has_aviz) {
                container.innerHTML = `
                    <button onclick="downloadDoc('${event.id}', 'aviz')" class="flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 text-sm font-medium rounded-lg hover:bg-green-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarca
                    </button>
                    <button onclick="regenerateDoc('${event.id}', 'aviz')" class="flex items-center gap-2 px-3 py-2 bg-amber-100 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Regenereaza
                    </button>
                `;
            } else {
                container.innerHTML = `
                    <button onclick="generateDoc('${event.id}', 'aviz')" class="flex items-center gap-2 px-4 py-2 bg-indigo-100 text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Genereaza
                    </button>
                `;
            }
        }

        function renderImpoziteActions(event) {
            const container = document.getElementById('impoziteActions');
            if (event.has_impozite) {
                container.innerHTML = `
                    <button onclick="downloadDoc('${event.id}', 'impozite')" class="flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 text-sm font-medium rounded-lg hover:bg-green-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarca
                    </button>
                    <button onclick="regenerateDoc('${event.id}', 'impozite')" class="flex items-center gap-2 px-3 py-2 bg-amber-100 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Regenereaza
                    </button>
                `;
            } else if (event.status === 'ended') {
                container.innerHTML = `
                    <button onclick="generateDoc('${event.id}', 'impozite')" class="flex items-center gap-2 px-4 py-2 bg-indigo-100 text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Genereaza
                    </button>
                `;
            } else {
                container.innerHTML = `
                    <span class="text-sm text-gray-400">Disponibil dupa terminarea evenimentului</span>
                `;
            }
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        function downloadDoc(eventId, type) {
            alert(`Se descarca documentul ${type} pentru evenimentul ${eventId}. (Demo)`);
        }

        function generateDoc(eventId, type) {
            alert(`Se genereaza documentul ${type} pentru evenimentul ${eventId}. (Demo)`);
        }

        function regenerateDoc(eventId, type) {
            if (confirm('Esti sigur ca vrei sa regenerezi acest document? Versiunea anterioara va fi inlocuita.')) {
                alert(`Se regenereaza documentul ${type} pentru evenimentul ${eventId}. (Demo)`);
            }
        }

        function downloadDocument(docId) {
            alert(`Se descarca documentul #${docId}. (Demo)`);
        }

        function regenerateDocument(docId) {
            if (confirm('Esti sigur ca vrei sa regenerezi acest document?')) {
                alert(`Se regenereaza documentul #${docId}. (Demo)`);
            }
        }

        function generateReport() {
            const eventId = document.getElementById('eventSelector').value;
            if (!eventId) {
                alert('Selecteaza mai intai un eveniment.');
                return;
            }
            window.location.href = `/organizator/raport?event=${eventId}`;
        }
    </script>
</body>
</html>
