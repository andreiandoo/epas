<?php
/**
 * TICS Organizer Invitations - Generate Event Invitations
 * Create and manage invitation codes and complimentary tickets
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Get event ID from URL
$eventId = isset($_GET['id']) ? htmlspecialchars($_GET['id']) : null;

// Demo event data
$event = [
    'id' => $eventId ?: 'coldplay-2026',
    'name' => 'Coldplay: Music of the Spheres',
    'date' => '2026-02-14',
    'venue' => 'Arena Nationala, Bucuresti',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop',
];

// Demo ticket types
$ticketTypes = [
    ['id' => 1, 'name' => 'General Access', 'price' => 350, 'available' => 2500],
    ['id' => 2, 'name' => 'Golden Circle', 'price' => 650, 'available' => 800],
    ['id' => 3, 'name' => 'VIP Experience', 'price' => 1200, 'available' => 150],
];

// Demo existing invitations
$invitations = [
    ['id' => 1, 'code' => 'PRESS-001', 'type' => 'Press', 'ticket' => 'VIP Experience', 'recipient' => 'Ion Popescu', 'email' => 'ion@press.ro', 'status' => 'sent', 'used' => false, 'created' => '2025-01-10'],
    ['id' => 2, 'code' => 'SPONSOR-001', 'type' => 'Sponsor', 'ticket' => 'Golden Circle', 'recipient' => 'Maria Ionescu', 'email' => 'maria@sponsor.com', 'status' => 'sent', 'used' => true, 'created' => '2025-01-08'],
    ['id' => 3, 'code' => 'VIP-001', 'type' => 'VIP Guest', 'ticket' => 'VIP Experience', 'recipient' => 'Andrei Vasilescu', 'email' => 'andrei@email.ro', 'status' => 'pending', 'used' => false, 'created' => '2025-01-15'],
    ['id' => 4, 'code' => 'PRESS-002', 'type' => 'Press', 'ticket' => 'Golden Circle', 'recipient' => 'Elena Dumitrescu', 'email' => 'elena@media.ro', 'status' => 'sent', 'used' => false, 'created' => '2025-01-12'],
];

// Current page for sidebar
$currentPage = 'events';

// Page config for head
$pageTitle = 'Generare Invitatii - ' . $event['name'];
$pageDescription = 'Creeaza invitatii si bilete gratuite';

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
                    <div class="flex items-center gap-4">
                        <a href="/organizator/evenimente" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Generare Invitatii</h1>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($event['name']) ?></p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <!-- Event Info Card -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <div class="flex items-center gap-4">
                        <img src="<?= htmlspecialchars($event['image']) ?>" alt="" class="w-20 h-20 rounded-xl object-cover">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($event['name']) ?></h2>
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mt-1">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <?= date('d M Y', strtotime($event['date'])) ?>
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <?= htmlspecialchars($event['venue']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?= count($invitations) ?></p>
                                <p class="text-xs text-gray-500">Invitatii generate</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?= count(array_filter($invitations, fn($i) => $i['status'] === 'sent')) ?></p>
                                <p class="text-xs text-gray-500">Trimise</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?= count(array_filter($invitations, fn($i) => $i['status'] === 'pending')) ?></p>
                                <p class="text-xs text-gray-500">In asteptare</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?= count(array_filter($invitations, fn($i) => $i['used'])) ?></p>
                                <p class="text-xs text-gray-500">Utilizate</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create New Invitation -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Creeaza invitatie noua</h3>
                    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tip invitatie *</label>
                            <select id="inviteType" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Selecteaza tipul</option>
                                <option value="press">Press / Media</option>
                                <option value="sponsor">Sponsor</option>
                                <option value="vip">VIP Guest</option>
                                <option value="artist">Artist / Staff</option>
                                <option value="promo">Promotional</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tip bilet *</label>
                            <select id="ticketType" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Selecteaza biletul</option>
                                <?php foreach ($ticketTypes as $ticket): ?>
                                <option value="<?= $ticket['id'] ?>"><?= htmlspecialchars($ticket['name']) ?> (<?= number_format($ticket['price'], 0, ',', '.') ?> RON)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nume destinatar *</label>
                            <input type="text" id="recipientName" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="ex: Ion Popescu">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email destinatar *</label>
                            <input type="email" id="recipientEmail" class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="email@exemplu.ro">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="createInvitation()" class="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Genereaza invitatie
                        </button>
                        <button onclick="showBulkModal()" class="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Import CSV
                        </button>
                    </div>
                </div>

                <!-- Invitations List -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900">Invitatii generate</h3>
                            <div class="flex items-center gap-2">
                                <button onclick="exportInvitations()" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Cod</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Tip</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Bilet</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Destinatar</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Utilizat</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($invitations as $inv): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-medium text-indigo-600"><?= htmlspecialchars($inv['code']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-600"><?= htmlspecialchars($inv['type']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($inv['ticket']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($inv['recipient']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($inv['email']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($inv['status'] === 'sent'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Trimis
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            In asteptare
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($inv['used']): ?>
                                        <span class="text-sm text-green-600 font-medium">Da</span>
                                        <?php else: ?>
                                        <span class="text-sm text-gray-400">Nu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ($inv['status'] === 'pending'): ?>
                                            <button onclick="sendInvitation(<?= $inv['id'] ?>)" class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Trimite">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="resendInvitation(<?= $inv['id'] ?>)" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Retrimite">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            </button>
                                            <button onclick="deleteInvitation(<?= $inv['id'] ?>)" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Sterge">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
        function createInvitation() {
            const type = document.getElementById('inviteType').value;
            const ticket = document.getElementById('ticketType').value;
            const name = document.getElementById('recipientName').value;
            const email = document.getElementById('recipientEmail').value;

            if (!type || !ticket || !name || !email) {
                alert('Te rugam sa completezi toate campurile obligatorii.');
                return;
            }

            alert(`Invitatie creata pentru ${name} (${email}). (Demo)`);
            // Clear form
            document.getElementById('inviteType').value = '';
            document.getElementById('ticketType').value = '';
            document.getElementById('recipientName').value = '';
            document.getElementById('recipientEmail').value = '';
        }

        function showBulkModal() {
            alert('Functionalitatea de import CSV va fi disponibila in curand. (Demo)');
        }

        function sendInvitation(id) {
            if (confirm('Trimiti invitatia pe email?')) {
                alert(`Invitatie #${id} trimisa cu succes. (Demo)`);
            }
        }

        function resendInvitation(id) {
            if (confirm('Retrimiti invitatia pe email?')) {
                alert(`Invitatie #${id} retrimisa. (Demo)`);
            }
        }

        function deleteInvitation(id) {
            if (confirm('Esti sigur ca vrei sa stergi aceasta invitatie?')) {
                alert(`Invitatie #${id} stearsa. (Demo)`);
            }
        }

        function exportInvitations() {
            alert('Se exporta lista de invitatii in format CSV. (Demo)');
        }
    </script>
</body>
</html>
