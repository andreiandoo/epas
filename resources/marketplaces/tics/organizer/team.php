<?php
/**
 * Organizer Team Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];
$teamMembers = $demoData['teamMembers'] ?? [];

// Current page for sidebar
$currentPage = 'team';

// Page config for head
$pageTitle = 'Echipa mea';
$pageDescription = 'Gestioneaza membrii echipei si permisiunile';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between px-8 py-4">
                <div><h1 class="text-2xl font-bold text-gray-900">Echipa mea</h1><p class="text-sm text-gray-500">Gestioneaza membrii echipei si permisiunile</p></div>
                <button onclick="document.getElementById('inviteModal').classList.remove('hidden')" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>Invita membru</button>
            </div>
        </header>

        <div class="p-8">
            <!-- Team Members -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-bold text-gray-900">Membri activi (4)</h2></div>
                <div class="divide-y divide-gray-100">
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50">
                        <img src="https://i.pravatar.cc/48?img=12" class="w-12 h-12 rounded-full">
                        <div class="flex-1">
                            <div class="flex items-center gap-2"><p class="font-medium text-gray-900">Alexandru Popescu</p><span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full">Admin</span></div>
                            <p class="text-sm text-gray-500">alexandru@liveevents.ro</p>
                        </div>
                        <p class="text-sm text-gray-400">Tu</p>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50">
                        <img src="https://i.pravatar.cc/48?img=5" class="w-12 h-12 rounded-full">
                        <div class="flex-1">
                            <div class="flex items-center gap-2"><p class="font-medium text-gray-900">Maria Ionescu</p><span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">Manager</span></div>
                            <p class="text-sm text-gray-500">maria@liveevents.ro</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg></button>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50">
                        <img src="https://i.pravatar.cc/48?img=33" class="w-12 h-12 rounded-full">
                        <div class="flex-1">
                            <div class="flex items-center gap-2"><p class="font-medium text-gray-900">Andrei Munteanu</p><span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">Staff</span></div>
                            <p class="text-sm text-gray-500">andrei@liveevents.ro</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg></button>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50">
                        <img src="https://i.pravatar.cc/48?img=20" class="w-12 h-12 rounded-full">
                        <div class="flex-1">
                            <div class="flex items-center gap-2"><p class="font-medium text-gray-900">Elena Dumitrescu</p><span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">Scanner</span></div>
                            <p class="text-sm text-gray-500">elena@liveevents.ro</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg></button>
                    </div>
                </div>
            </div>

            <!-- Roles Info -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-bold text-gray-900 mb-4">Despre roluri</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-indigo-50 rounded-xl">
                        <span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">Admin</span>
                        <p class="text-sm text-gray-600 mt-2">Acces complet: evenimente, finante, setari, echipa</p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-xl">
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">Manager</span>
                        <p class="text-sm text-gray-600 mt-2">Gestioneaza evenimente si comenzi, fara acces finante</p>
                    </div>
                    <div class="p-4 bg-amber-50 rounded-xl">
                        <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">Staff</span>
                        <p class="text-sm text-gray-600 mt-2">Vizualizeaza comenzi si participanti</p>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-xl">
                        <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-full">Scanner</span>
                        <p class="text-sm text-gray-600 mt-2">Doar acces la scanner pentru check-in</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Invite Modal -->
    <div id="inviteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 animate-scaleIn">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Invita membru nou</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-2">Email</label><input type="email" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" placeholder="email@exemplu.ro"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-2">Rol</label><select class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none"><option>Manager</option><option>Staff</option><option>Scanner</option></select></div>
            </div>
            <div class="flex gap-3 mt-6">
                <button onclick="document.getElementById('inviteModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200">Anuleaza</button>
                <button class="flex-1 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700">Trimite invitatie</button>
            </div>
        </div>
    </div>
</body>
</html>
