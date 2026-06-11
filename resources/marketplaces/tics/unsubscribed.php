<?php
/**
 * TICS.ro - Unsubscribed Confirmation Page
 * Undo toast, wave animation, resubscribe option
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$pageTitle = 'Dezabonat';
$pageDescription = 'Te-ai dezabonat cu succes de la newsletterul TICS.';
$bodyClass = 'bg-gray-50 min-h-screen flex flex-col';
$noIndex = true;

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Dezabonat', 'url' => null],
];

// Demo data
$userEmail = $_GET['email'] ?? 'andrei@email.com';

$transactionalEmails = [
    'Confirmări de achiziție și bilete',
    'Facturi și documente fiscale',
    'Alerte de securitate cont',
    'Notificări despre bilete transferate',
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
        <div class="w-full max-w-md text-center">

            <!-- Undo toast -->
            <div class="anim bg-gray-900 rounded-2xl px-5 py-4 flex items-center gap-3 mb-8 shadow-lg relative" id="undoToast">
                <div class="flex-1 text-left">
                    <p class="text-sm text-white font-medium">Te-ai dezabonat cu succes</p>
                    <p class="text-xs text-gray-400 mt-0.5">Ai schimbat parerea?</p>
                </div>
                <button onclick="resubscribe()" class="px-4 py-2 bg-white text-gray-900 text-sm font-bold rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0">Anuleaza</button>
                <div class="absolute bottom-0 left-4 right-4 h-0.5 bg-gray-700 rounded overflow-hidden">
                    <div id="undoBar" class="undo-bar h-full bg-indigo-400 rounded" style="width:100%"></div>
                </div>
            </div>

            <!-- Main card -->
            <div class="anim ad1 bg-white rounded-2xl border border-gray-200 shadow-sm p-8 lg:p-10 relative overflow-hidden">
                <!-- Subtle confetti -->
                <div class="absolute inset-0 pointer-events-none overflow-hidden" id="confetti"></div>

                <div class="relative">
                    <div class="text-5xl mb-5"><span class="wave">&#x1F44B;</span></div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Ne va fi dor de tine!</h1>
                    <p class="text-sm text-gray-500 mb-2">Adresa <strong class="text-gray-700"><?= e($userEmail) ?></strong> a fost dezabonata de la newsletterul TICS.</p>
                    <p class="text-sm text-gray-500 mb-8">Schimbarea va fi activa imediat. Este posibil sa mai primesti 1-2 emailuri deja programate.</p>

                    <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-100">
                        <p class="text-xs font-medium text-gray-900 mb-2">Ce nu se schimba:</p>
                        <div class="space-y-2 text-xs text-gray-600 text-left">
                            <?php foreach ($transactionalEmails as $item): ?>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <?= e($item) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button onclick="resubscribe()" class="resubscribe-btn w-full py-3 bg-gray-900 text-white text-sm font-semibold rounded-xl mb-3 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Re-aboneaza-ma
                    </button>
                    <a href="/" class="block text-sm text-gray-500 hover:text-gray-900 transition-colors">Inapoi la TICS.ro &rarr;</a>
                </div>
            </div>

            <p class="anim ad2 text-xs text-gray-400 mt-6">Daca crezi ca primesti emailuri nedorite, <a href="/contact" class="text-indigo-600 hover:underline">contacteaza-ne</a>.</p>
        </div>
    </main>

    <script>
    // Undo timer countdown
    let undoTime=10;const undoBar=document.getElementById('undoBar');const undoToast=document.getElementById('undoToast');
    const undoInterval=setInterval(()=>{undoTime--;undoBar.style.width=(undoTime/10*100)+'%';if(undoTime<=0){clearInterval(undoInterval);undoToast.style.opacity='0';undoToast.style.transform='translateY(-10px)';setTimeout(()=>undoToast.style.display='none',300)}},1000);

    function resubscribe(){
        clearInterval(undoInterval);
        undoToast.innerHTML='<div class="flex items-center gap-2"><svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg><span class="text-sm text-white font-medium">Te-ai re-abonat cu succes!</span></div>';
        setTimeout(()=>{undoToast.style.opacity='0';setTimeout(()=>undoToast.style.display='none',300)},3000);
    }
    </script>
</body>
</html>
