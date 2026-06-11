<?php
/**
 * TICS.ro - Refund Policy
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Politica de rambursare';
$pageDescription = 'Informații despre rambursări și returnarea biletelor pe TICS.ro.';

$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
</style>
HTML;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-green-600 via-emerald-600 to-teal-600 py-16">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full text-white text-sm mb-4 animate-fadeInUp">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Garanție de rambursare
        </div>
        <h1 class="text-4xl font-bold text-white mb-3 animate-fadeInUp" style="animation-delay: 0.1s">Politica de rambursare</h1>
        <p class="text-white/80 text-lg animate-fadeInUp" style="animation-delay: 0.2s">Transparență și simplitate în procesul de returnare</p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 lg:px-8 py-12">
    <!-- Quick Summary -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8 animate-fadeInUp">
        <h2 class="text-lg font-bold text-gray-900 mb-4">📋 Rezumat rapid</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-green-50 rounded-xl">
                <div class="text-3xl font-bold text-green-600 mb-1">100%</div>
                <p class="text-sm text-gray-600">Rambursare la anularea evenimentului</p>
            </div>
            <div class="text-center p-4 bg-amber-50 rounded-xl">
                <div class="text-3xl font-bold text-amber-600 mb-1">14 zile</div>
                <p class="text-sm text-gray-600">Termen de procesare</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-xl">
                <div class="text-3xl font-bold text-blue-600 mb-1">0 RON</div>
                <p class="text-sm text-gray-600">Taxe pentru rambursare</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-8 animate-fadeInUp" style="animation-delay: 0.1s">
        <!-- When you get refund -->
        <section class="mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">✓</span>
                Când primești rambursare
            </h2>
            <div class="space-y-4">
                <div class="flex items-start gap-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Eveniment anulat definitiv</h4>
                        <p class="text-sm text-gray-600 mt-1">Primești automat 100% din suma plătită, inclusiv comisionul de serviciu. Rambursarea se procesează în maxim 14 zile lucrătoare.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Eveniment reprogramat (dacă nu poți participa)</h4>
                        <p class="text-sm text-gray-600 mt-1">Dacă noua dată nu ți se potrivește, poți solicita rambursare în termen de 14 zile de la anunțul reprogramării.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Eroare la procesarea comenzii</h4>
                        <p class="text-sm text-gray-600 mt-1">Dacă ai fost taxat dublu sau a apărut o eroare tehnică, rezolvăm imediat și rambursăm integral.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- When you don't get refund -->
        <section class="mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">✕</span>
                Când NU primești rambursare
            </h2>
            <div class="space-y-4">
                <div class="flex items-start gap-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Te-ai răzgândit</h4>
                        <p class="text-sm text-gray-600 mt-1">Biletele pentru evenimente sunt exceptate de la dreptul de retragere conform OUG 34/2014, deoarece au o dată fixă de utilizare.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Nu ai putut participa din motive personale</h4>
                        <p class="text-sm text-gray-600 mt-1">Probleme de sănătate, transport sau alte circumstanțe personale nu califică pentru rambursare.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Bilet folosit sau expirat</h4>
                        <p class="text-sm text-gray-600 mt-1">Biletele scanate la intrare sau evenimentele la care nu ai participat nu pot fi rambursate.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Process -->
        <section class="mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">📝 Cum soliciți rambursarea</h2>
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">1</div>
                    <div class="flex-1 p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-700">Accesează <strong>Contul meu → Comenzi</strong> și găsește comanda relevantă</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">2</div>
                    <div class="flex-1 p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-700">Click pe <strong>„Solicită rambursare"</strong> și selectează motivul</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">3</div>
                    <div class="flex-1 p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-700">Primești confirmare pe email și banii în <strong>14 zile lucrătoare</strong></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">🎫 Întrebări frecvente</h2>
            <div class="space-y-4">
                <details class="group bg-gray-50 rounded-xl">
                    <summary class="flex items-center justify-between p-4 cursor-pointer">
                        <span class="font-medium text-gray-900">Ce se întâmplă dacă organizatorul oferă voucher în loc de bani?</span>
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-4 pb-4 text-sm text-gray-600">
                        Ai dreptul să refuzi și să soliciți rambursare în bani dacă evenimentul a fost anulat.
                    </div>
                </details>
                <details class="group bg-gray-50 rounded-xl">
                    <summary class="flex items-center justify-between p-4 cursor-pointer">
                        <span class="font-medium text-gray-900">Pot transfera biletul în loc să-l returnez?</span>
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-4 pb-4 text-sm text-gray-600">
                        Da! Poți transfera biletul gratuit către altă persoană din secțiunea „Biletele mele".
                    </div>
                </details>
                <details class="group bg-gray-50 rounded-xl">
                    <summary class="flex items-center justify-between p-4 cursor-pointer">
                        <span class="font-medium text-gray-900">Am plătit cu cardul. Unde primesc banii înapoi?</span>
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-4 pb-4 text-sm text-gray-600">
                        Rambursarea se face pe același card cu care ai plătit.
                    </div>
                </details>
            </div>
        </section>
    </div>

    <!-- Contact -->
    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6 animate-fadeInUp" style="animation-delay: 0.2s">
        <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-sm">
                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h3 class="font-semibold text-gray-900">Ai întrebări despre rambursare?</h3>
                <p class="text-sm text-gray-600">Echipa noastră de suport îți răspunde în maxim 24 de ore</p>
            </div>
            <a href="mailto:support@tics.ro" class="px-5 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                Contactează-ne
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
