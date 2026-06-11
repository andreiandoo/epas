<?php
/**
 * TICS.ro - Newsletter Unsubscribe Page
 * Frequency options, unsubscribe reasons
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$pageTitle = 'Dezabonare newsletter';
$pageDescription = 'Gestionează-ți preferințele de email sau dezabonează-te de la newsletterul TICS.';
$bodyClass = 'bg-gray-50 min-h-screen flex flex-col';
$noIndex = true;

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Dezabonare newsletter', 'url' => null],
];

// Demo data
$userEmail = $_GET['email'] ?? 'andrei@email.com';

$frequencyOptions = [
    ['label' => 'Săptămânal', 'value' => 'weekly'],
    ['label' => 'Lunar', 'value' => 'monthly'],
    ['label' => 'Doar oferte', 'value' => 'offers_only'],
];
$defaultFrequency = 'monthly';

$unsubscribeReasons = [
    'Primesc prea multe emailuri',
    'Conținutul nu este relevant pentru mine',
    'Nu am creat eu acest abonament',
    'Alt motiv',
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
?>

    <!-- Minimal centered header for standalone page -->
    <header class="bg-white border-b border-gray-200 flex-shrink-0">
        <div class="max-w-lg mx-auto px-4">
            <div class="flex items-center justify-center h-16">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">T</span>
                    </div>
                    <span class="font-bold text-lg">TICS</span>
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg">
            <!-- Card -->
            <div class="anim bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <!-- Top section -->
                <div class="p-6 lg:p-8 text-center border-b border-gray-100">
                    <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-5">&#x1F4EC;</div>
                    <h1 class="text-xl font-bold text-gray-900 mb-2">Vrei sa te dezabonezi?</h1>
                    <p class="text-sm text-gray-500">Email: <strong class="text-gray-700"><?= e($userEmail) ?></strong></p>
                    <p class="text-sm text-gray-500 mt-1">Inainte de a pleca, poate preferi sa ajustezi frecventa?</p>
                </div>

                <!-- Frequency options -->
                <div class="p-6 lg:p-8 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-900 mb-3">Ajusteaza frecventa emailurilor</p>
                    <div class="grid grid-cols-3 gap-2 mb-5">
                        <?php foreach ($frequencyOptions as $freq): ?>
                        <button class="freq-btn<?= $freq['value'] === $defaultFrequency ? ' active' : '' ?> px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-600" onclick="selectFreq(this)"><?= e($freq['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <button class="keep-btn w-full py-3 bg-gray-900 text-white text-sm font-semibold rounded-xl flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Salveaza preferinta si raman abonat
                    </button>
                </div>

                <!-- Unsubscribe reasons -->
                <div class="p-6 lg:p-8">
                    <p class="text-sm font-semibold text-gray-900 mb-1">Totusi vreau sa ma dezabonez</p>
                    <p class="text-xs text-gray-400 mb-4">Spune-ne de ce, ca sa ne imbunatatim (optional):</p>

                    <div class="space-y-2 mb-5" id="reasons">
                        <?php foreach ($unsubscribeReasons as $reason): ?>
                        <label class="option-card flex items-center gap-3 p-3 border border-gray-200 rounded-xl" onclick="selectReason(this)">
                            <div class="check-circle">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-sm text-gray-700"><?= e($reason) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <a href="/dezabonat" class="unsub-btn block w-full py-3 bg-red-500 text-white text-sm font-semibold rounded-xl text-center">
                        Dezaboneaza-ma complet
                    </a>
                    <p class="text-[11px] text-gray-400 text-center mt-3">Vei primi in continuare emailuri tranzactionale (bilete, facturi, confirmari).</p>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                <a href="/" class="hover:text-gray-600 transition-colors">TICS.ro</a> · <a href="/confidentialitate" class="hover:text-gray-600 transition-colors">Politica de confidentialitate</a>
            </p>
        </div>
    </main>

    <script>
    function selectReason(el){document.querySelectorAll('.option-card').forEach(c=>c.classList.remove('selected'));el.classList.add('selected')}
    function selectFreq(el){document.querySelectorAll('.freq-btn').forEach(b=>{b.classList.remove('active')});el.classList.add('active')}
    </script>
</body>
</html>
