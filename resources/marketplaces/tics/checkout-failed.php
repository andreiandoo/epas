<?php
/**
 * TICS.ro - Checkout Failed / Payment Error Page
 *
 * Displayed when a payment attempt fails during checkout.
 * Shows error details, reservation timer, retry options, and support info.
 */

require_once __DIR__ . '/includes/config.php';

// ============================================================================
// DEMO ORDER DATA
// ============================================================================

$orderEventName = 'Carla\'s Dreams — Live';
$orderVenue = 'Sala Palatului, București';
$orderDate = '14 martie 2026';
$orderTicketLabel = '2× Categorie A';
$orderTicketCount = 2;
$orderPrice = 380;
$orderReference = '#ORD-2026-8f3a';

// Error details
$errorTitle = 'Card refuzat de bancă';
$errorDescription = 'Fonduri insuficiente sau limita de tranzacții online depășită.';
$errorCode = 'CARD_DECLINED_INSUFFICIENT';

// Reservation timer (seconds remaining)
$timerSeconds = 587;
$timerTotal = 600;
$timerPercent = round(($timerSeconds / $timerTotal) * 100);

// ============================================================================
// PAGE CONFIGURATION
// ============================================================================

$pageTitle = 'Plată nereușită';
$pageDescription = 'Plata nu a fost procesată. Biletele sunt încă rezervate — încearcă din nou cu o altă metodă de plată.';
$bodyClass = 'bg-gray-50 min-h-screen';
$hideCategoriesBar = true;

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Checkout', 'url' => '/checkout'],
    ['name' => 'Plată nereușită'],
];

// ============================================================================
// SET LOGIN STATE & INCLUDE PARTIALS
// ============================================================================

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <main class="max-w-3xl mx-auto px-4 lg:px-8 py-10 lg:py-16">

        <!-- Error Icon + Message -->
        <div class="text-center mb-8">
            <div class="anim shake inline-flex items-center justify-center w-20 h-20 bg-red-50 rounded-full mb-5 pulse-border border-2 border-red-200">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <h1 class="anim ad1 text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Plata nu a fost procesată</h1>
            <p class="anim ad2 text-gray-500 max-w-md mx-auto">Nu îți face griji — nu ai fost debitat. Biletele sunt încă rezervate pentru tine.</p>
        </div>

        <!-- Reservation Timer -->
        <div class="anim ad2 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center gap-4 mb-6 max-w-lg mx-auto">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-amber-900">Biletele sunt rezervate încă <span class="countdown font-bold" id="timer"><?= str_pad(floor($timerSeconds / 60), 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($timerSeconds % 60, 2, '0', STR_PAD_LEFT) ?></span></p>
                <div class="w-full bg-amber-200 rounded-full h-1.5 mt-2">
                    <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-1000" id="timerBar" style="width:<?= e($timerPercent) ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Order Summary Card -->
        <div class="anim ad3 bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
            <div class="p-5 border-b border-gray-100">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Comanda ta</p>
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-indigo-100 to-violet-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🎵</div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900"><?= e($orderEventName) ?></h3>
                        <p class="text-sm text-gray-500"><?= e($orderVenue) ?> · <?= e($orderDate) ?></p>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-xs text-gray-400"><?= e($orderTicketLabel) ?></span>
                            <span class="text-xs font-semibold text-gray-900"><?= e(formatPrice($orderPrice)) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Details -->
            <div class="p-5 bg-red-50/50 border-b border-gray-100">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <div>
                        <p class="text-sm font-medium text-red-800 mb-0.5"><?= e($errorTitle) ?></p>
                        <p class="text-xs text-red-600">Eroare: <?= e($errorDescription) ?> Cod: <?= e($errorCode) ?></p>
                    </div>
                </div>
            </div>

            <!-- Retry section -->
            <div class="p-5">
                <p class="text-sm font-semibold text-gray-900 mb-3">Încearcă din nou cu:</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
                    <label class="method-btn selected block border border-gray-200 rounded-xl p-3 text-center">
                        <input type="radio" name="method" class="sr-only" checked>
                        <div class="text-2xl mb-1">💳</div>
                        <p class="text-xs font-medium text-gray-700">Alt card</p>
                    </label>
                    <label class="method-btn block border border-gray-200 rounded-xl p-3 text-center">
                        <input type="radio" name="method" class="sr-only">
                        <div class="text-2xl mb-1">🏦</div>
                        <p class="text-xs font-medium text-gray-700">Transfer bancar</p>
                    </label>
                    <label class="method-btn block border border-gray-200 rounded-xl p-3 text-center">
                        <input type="radio" name="method" class="sr-only">
                        <div class="text-2xl mb-1"></div>
                        <p class="text-xs font-medium text-gray-700">Apple Pay</p>
                    </label>
                    <label class="method-btn block border border-gray-200 rounded-xl p-3 text-center">
                        <input type="radio" name="method" class="sr-only">
                        <div class="text-2xl mb-1">🎁</div>
                        <p class="text-xs font-medium text-gray-700">Gift Card</p>
                    </label>
                </div>
                <button class="w-full py-3.5 bg-gray-900 text-white text-sm font-semibold rounded-xl hover:bg-gray-800 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reîncearcă plata — <?= e(formatPrice($orderPrice)) ?>
                </button>
                <p class="text-center text-xs text-gray-400 mt-3">Plata este securizată cu criptare SSL 256-bit</p>
            </div>
        </div>

        <!-- Tips -->
        <div class="anim ad4">
            <p class="text-sm font-semibold text-gray-900 mb-3">Sfaturi rapide</p>
            <div class="grid sm:grid-cols-3 gap-3 mb-10">
                <div class="tip-card bg-white rounded-xl border border-gray-200 p-4">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center text-lg mb-2">🔒</div>
                    <p class="text-xs text-gray-600 leading-relaxed"><strong class="text-gray-900">Verifică limita online.</strong> Unele bănci au o limită zilnică pentru tranzacții online. Contactează banca sau mărește limita din aplicația bancară.</p>
                </div>
                <div class="tip-card bg-white rounded-xl border border-gray-200 p-4">
                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center text-lg mb-2">💰</div>
                    <p class="text-xs text-gray-600 leading-relaxed"><strong class="text-gray-900">Verifică soldul.</strong> Asigură-te că ai suficiente fonduri pe card. Unele bănci blochează temporar suma chiar dacă plata eșuează.</p>
                </div>
                <div class="tip-card bg-white rounded-xl border border-gray-200 p-4">
                    <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center text-lg mb-2">🔄</div>
                    <p class="text-xs text-gray-600 leading-relaxed"><strong class="text-gray-900">Schimbă metoda.</strong> Folosește un alt card, transfer bancar sau Apple Pay / Google Pay ca alternativă.</p>
                </div>
            </div>
        </div>

        <!-- Support strip -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col sm:flex-row items-center gap-4">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Ai nevoie de ajutor cu plata?</p>
                <p class="text-xs text-gray-500">Echipa noastră de suport e disponibilă. Referință comandă: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs font-mono"><?= e($orderReference) ?></code></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="#" class="px-4 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">💬 Live chat</a>
                <a href="#" class="px-4 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">✉️ Email</a>
            </div>
        </div>
    </main>

<script>
// Countdown timer
let total = <?= (int)$timerSeconds ?>;
const timerTotal = <?= (int)$timerTotal ?>;
const tick = () => {
    if (total <= 0) return;
    total--;
    const m = Math.floor(total / 60);
    const s = total % 60;
    document.getElementById('timer').textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    document.getElementById('timerBar').style.width = ((total / timerTotal) * 100) + '%';
    setTimeout(tick, 1000);
};
tick();

// Method select
document.querySelectorAll('.method-btn').forEach(b => {
    b.addEventListener('click', () => {
        document.querySelectorAll('.method-btn').forEach(x => x.classList.remove('selected'));
        b.classList.add('selected');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
