<?php
/**
 * Organizer Events Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];
$events = $demoData['events'];

// Current page for sidebar
$currentPage = 'events';

// Page config for head
$pageTitle = 'Evenimente';
$pageDescription = 'Gestioneaza toate evenimentele tale';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between px-8 py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Evenimente</h1>
                    <p class="text-sm text-gray-500">Gestioneaza toate evenimentele tale</p>
                </div>
                <a href="/organizator/eveniment-nou" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Eveniment nou
                </a>
            </div>
        </header>

        <div class="p-8">
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-6 animate-fadeInUp">
                <div class="flex items-center gap-2">
                    <?php
                    $activeCount = count(array_filter($events, fn($e) => $e['status'] === 'active'));
                    $draftCount = count(array_filter($events, fn($e) => $e['status'] === 'draft'));
                    $endedCount = count(array_filter($events, fn($e) => $e['status'] === 'ended'));
                    ?>
                    <button class="filter-btn active px-4 py-2 text-sm font-medium rounded-full transition-colors" data-filter="all">Toate (<?= count($events) ?>)</button>
                    <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-600 rounded-full hover:bg-gray-100 transition-colors" data-filter="active">Active (<?= $activeCount ?>)</button>
                    <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-600 rounded-full hover:bg-gray-100 transition-colors" data-filter="draft">Ciorna (<?= $draftCount ?>)</button>
                    <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-600 rounded-full hover:bg-gray-100 transition-colors" data-filter="ended">Incheiate (<?= $endedCount ?>)</button>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <div class="relative">
                        <input type="text" placeholder="Cauta evenimente..." class="pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-64">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <select class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option>Cele mai recente</option>
                        <option>Data evenimentului</option>
                        <option>Cele mai vandute</option>
                    </select>
                </div>
            </div>

            <!-- Events Grid -->
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($events as $index => $event): ?>
                <div class="event-card bg-white rounded-2xl border border-gray-200 overflow-hidden <?= $event['status'] !== 'active' ? 'opacity-75' : '' ?> animate-fadeInUp"
                     style="animation-delay: <?= 0.1 + ($index * 0.05) ?>s"
                     data-status="<?= htmlspecialchars($event['status']) ?>">
                    <div class="relative">
                        <img src="<?= htmlspecialchars($event['image']) ?>" class="w-full h-40 object-cover <?= $event['status'] !== 'active' ? 'grayscale' : '' ?>" alt="<?= htmlspecialchars($event['name']) ?>">
                        <?php if ($event['status'] === 'active'): ?>
                        <span class="absolute top-3 left-3 px-2 py-1 bg-green-500 text-white text-xs font-bold rounded-full">Activ</span>
                        <?php elseif ($event['status'] === 'draft'): ?>
                        <span class="absolute top-3 left-3 px-2 py-1 bg-gray-500 text-white text-xs font-bold rounded-full">Ciorna</span>
                        <?php else: ?>
                        <span class="absolute top-3 left-3 px-2 py-1 bg-gray-700 text-white text-xs font-bold rounded-full">Incheiat</span>
                        <?php endif; ?>
                        <?php if (isset($event['trending']) && $event['trending']): ?>
                        <span class="absolute top-3 right-3 px-2 py-1 bg-black/50 text-white text-xs font-medium rounded-full">Trending</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <h3 class="font-bold text-gray-900 mb-1"><?= htmlspecialchars($event['name']) ?></h3>
                        <p class="text-sm text-gray-500 mb-3"><?= date('j M Y', strtotime($event['date'])) ?> • <?= htmlspecialchars($event['venue']) ?></p>

                        <?php if ($event['status'] !== 'draft'): ?>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-xs text-gray-500">Bilete vandute</p>
                                <p class="text-lg font-bold text-gray-900"><?= number_format($event['ticketsSold']) ?> <span class="text-sm font-normal text-gray-500">/ <?= number_format($event['ticketsTotal']) ?></span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Venituri</p>
                                <p class="text-lg font-bold <?= $event['status'] === 'active' ? 'text-green-600' : 'text-gray-600' ?>"><?= number_format($event['revenue'], 0, ',', '.') ?> RON</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                            <?php
                            $progressColor = $event['soldPercentage'] >= 80 ? 'bg-amber-500' : ($event['soldPercentage'] >= 100 ? 'bg-green-500' : 'bg-indigo-600');
                            ?>
                            <div class="<?= $progressColor ?> h-2 rounded-full" style="width: <?= $event['soldPercentage'] ?>%"></div>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="text-sm font-medium text-amber-600">Incomplet - necesita detalii</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-2">
                            <?php if ($event['status'] === 'draft'): ?>
                            <a href="/organizator/edit/<?= htmlspecialchars($event['id']) ?>" class="flex-1 py-2 text-center bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">Continua editarea</a>
                            <button type="button" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <?php elseif ($event['status'] === 'ended'): ?>
                            <a href="/organizator/report/<?= htmlspecialchars($event['id']) ?>" class="flex-1 py-2 text-center bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">Vezi raport</a>
                            <button type="button" class="flex-1 py-2 text-center bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">Duplica</button>
                            <div class="relative">
                                <button type="button" class="event-menu-btn p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl" data-event-id="<?= htmlspecialchars($event['id']) ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                </button>
                                <div class="event-menu hidden absolute right-0 bottom-full mb-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-10">
                                    <a href="/organizator/documents/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Documente
                                    </a>
                                    <a href="/organizator/invitations/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Generare invitatii
                                    </a>
                                    <a href="/organizator/sales/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Vanzari
                                    </a>
                                </div>
                            </div>
                            <?php else: ?>
                            <a href="/organizator/edit/<?= htmlspecialchars($event['id']) ?>" class="flex-1 py-2 text-center bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">Editeaza</a>
                            <a href="/organizator/analytics/<?= htmlspecialchars($event['id']) ?>" class="flex-1 py-2 text-center bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">Statistici</a>
                            <div class="relative">
                                <button type="button" class="event-menu-btn p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl" data-event-id="<?= htmlspecialchars($event['id']) ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                </button>
                                <div class="event-menu hidden absolute right-0 bottom-full mb-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-10">
                                    <a href="/organizator/documents/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Documente
                                    </a>
                                    <a href="/organizator/invitations/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Generare invitatii
                                    </a>
                                    <a href="/organizator/sales/<?= htmlspecialchars($event['id']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Vanzari
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add New Event Card -->
                <a href="/organizator/eveniment-nou" class="event-card flex flex-col items-center justify-center bg-white rounded-2xl border-2 border-dashed border-gray-300 p-8 hover:border-indigo-400 hover:bg-indigo-50/50 transition-all animate-fadeInUp" style="animation-delay: <?= 0.1 + (count($events) * 0.05) ?>s">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-1">Creeaza eveniment nou</h3>
                    <p class="text-sm text-gray-500 text-center">Adauga un nou eveniment si incepe sa vinzi bilete</p>
                </a>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    document.querySelectorAll('.event-card[data-status]').forEach(card => {
                        if (filter === 'all' || card.dataset.status === filter) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });

            // Event menu dropdowns
            document.querySelectorAll('.event-menu-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const menu = this.nextElementSibling;

                    // Close all other menus
                    document.querySelectorAll('.event-menu').forEach(m => {
                        if (m !== menu) m.classList.add('hidden');
                    });

                    // Toggle this menu
                    menu.classList.toggle('hidden');
                });
            });

            // Close menus when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.event-menu-btn') && !e.target.closest('.event-menu')) {
                    document.querySelectorAll('.event-menu').forEach(menu => {
                        menu.classList.add('hidden');
                    });
                }
            });
        });
    </script>
</body>
</html>
