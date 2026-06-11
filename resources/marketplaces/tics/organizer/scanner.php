<?php
/**
 * Organizer Scanner Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];
$stats = $demoData['stats'];
$events = $demoData['events'];

// Current page for sidebar (not used here but included for consistency)
$currentPage = 'scanner';

// Page config for head
$pageTitle = 'Scanner Bilete';
$pageDescription = 'Check-in participanti';
$bodyClass = 'bg-gray-900';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/organizator" class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-xl"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
                <div><span class="font-bold text-white">Scanner TICS</span><p class="text-xs text-gray-400">Check-in participanti</p></div>
            </div>
            <select class="bg-gray-700 border border-gray-600 text-white text-sm rounded-xl px-4 py-2">
                <?php foreach ($events as $event): ?>
                <option value="<?= htmlspecialchars($event['id']) ?>"><?= htmlspecialchars($event['name']) ?> • <?= date('j M', strtotime($event['date'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-gray-800 rounded-xl p-4 text-center">
                <p class="text-3xl font-bold text-white" id="totalTickets">1.245</p>
                <p class="text-xs text-gray-400">Total bilete</p>
            </div>
            <div class="bg-gray-800 rounded-xl p-4 text-center">
                <p class="text-3xl font-bold text-green-400" id="checkedIn">847</p>
                <p class="text-xs text-gray-400">Check-in facut</p>
            </div>
            <div class="bg-gray-800 rounded-xl p-4 text-center">
                <p class="text-3xl font-bold text-amber-400" id="pending">398</p>
                <p class="text-xs text-gray-400">In asteptare</p>
            </div>
        </div>

        <!-- Scanner Area -->
        <div class="bg-gray-800 rounded-2xl p-8 mb-6">
            <div id="scannerDefault" class="text-center">
                <div class="relative w-48 h-48 mx-auto mb-6">
                    <div class="absolute inset-0 border-4 border-indigo-500 rounded-3xl"></div>
                    <div class="absolute inset-2 border-4 border-indigo-500/50 rounded-2xl scan-ring"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-16 h-16 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">Scaneaza un bilet</h2>
                <p class="text-gray-400 mb-6">Foloseste camera sau introdu codul manual</p>
                <button onclick="startCamera()" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 flex items-center justify-center gap-2 mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Deschide camera
                </button>
                <button onclick="showManualInput()" class="w-full py-3 bg-gray-700 text-white font-medium rounded-xl hover:bg-gray-600">Introdu cod manual</button>
            </div>

            <!-- Manual Input -->
            <div id="manualInput" class="hidden text-center">
                <h2 class="text-xl font-bold text-white mb-4">Introdu codul biletului</h2>
                <input type="text" id="ticketCode" placeholder="ex: TCS-2026-00892-A1" class="w-full px-4 py-4 bg-gray-700 border border-gray-600 rounded-xl text-white text-center text-lg font-mono uppercase tracking-wider mb-4 focus:outline-none focus:border-indigo-500">
                <button onclick="validateTicket()" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 mb-3">Valideaza bilet</button>
                <button onclick="showDefault()" class="text-gray-400 hover:text-white text-sm">← Inapoi la scanner</button>
            </div>

            <!-- Success State -->
            <div id="successState" class="hidden text-center success-anim">
                <div class="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-green-400 mb-2">Bilet valid!</h2>
                <div class="bg-gray-700 rounded-xl p-4 mb-6">
                    <p class="text-white font-medium" id="ticketHolderName">Maria Ionescu</p>
                    <p class="text-gray-400 text-sm" id="ticketDetails">Bilet VIP • #TCS-2026-00892-A1</p>
                </div>
                <button onclick="showDefault()" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700">Scaneaza alt bilet</button>
            </div>

            <!-- Error State -->
            <div id="errorState" class="hidden text-center">
                <div class="w-24 h-24 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-red-400 mb-2">Bilet invalid!</h2>
                <p class="text-gray-400 mb-6" id="errorMessage">Acest bilet a fost deja folosit sau nu exista.</p>
                <button onclick="showDefault()" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700">Incearca din nou</button>
            </div>
        </div>

        <!-- Recent Scans -->
        <div class="bg-gray-800 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h3 class="font-bold text-white">Scanari recente</h3>
            </div>
            <div class="divide-y divide-gray-700" id="recentScans">
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                    <div class="flex-1"><p class="text-white font-medium">Maria Ionescu</p><p class="text-xs text-gray-400">VIP • acum 2 min</p></div>
                    <span class="text-green-400 text-sm">Valid</span>
                </div>
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                    <div class="flex-1"><p class="text-white font-medium">Andrei Popescu</p><p class="text-xs text-gray-400">General • acum 5 min</p></div>
                    <span class="text-green-400 text-sm">Valid</span>
                </div>
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="w-10 h-10 bg-red-500/20 rounded-full flex items-center justify-center"><svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></div>
                    <div class="flex-1"><p class="text-white font-medium">Cod necunoscut</p><p class="text-xs text-gray-400">acum 8 min</p></div>
                    <span class="text-red-400 text-sm">Invalid</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showManualInput() {
            document.getElementById('scannerDefault').classList.add('hidden');
            document.getElementById('manualInput').classList.remove('hidden');
        }

        function showDefault() {
            document.querySelectorAll('#scannerDefault, #manualInput, #successState, #errorState').forEach(el => el.classList.add('hidden'));
            document.getElementById('scannerDefault').classList.remove('hidden');
            document.getElementById('ticketCode').value = '';
        }

        function validateTicket() {
            const code = document.getElementById('ticketCode').value.trim();
            document.querySelectorAll('#scannerDefault, #manualInput, #successState, #errorState').forEach(el => el.classList.add('hidden'));

            if (code.length > 5) {
                // Simulate success
                document.getElementById('successState').classList.remove('hidden');
                document.getElementById('ticketHolderName').textContent = 'Client ' + code.slice(-4);
                document.getElementById('ticketDetails').textContent = 'Bilet General • #' + code;
            } else {
                // Simulate error
                document.getElementById('errorState').classList.remove('hidden');
            }
        }

        function startCamera() {
            alert('Camera activata! (Demo - in productie se va deschide camera reala cu biblioteca QR scanner)');
        }
    </script>
</body>
</html>
