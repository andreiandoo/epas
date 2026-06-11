<?php
/**
 * TICS.ro - Thank You / Order Confirmation Page
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Comandă confirmată!';
$pageDescription = 'Comanda ta a fost finalizată cu succes.';

$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
    @keyframes confetti { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(-200px) rotate(720deg); opacity: 0; } }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

    .animate-fadeInUp { animation: fadeInUp 0.6s ease forwards; }
    .animate-scaleIn { animation: scaleIn 0.5s ease forwards; }
    .animate-float { animation: float 3s ease-in-out infinite; }

    .success-circle { animation: scaleIn 0.5s ease 0.2s forwards; transform: scale(0); }
    .checkmark-path { stroke-dasharray: 100; stroke-dashoffset: 100; animation: checkmark 0.5s ease 0.5s forwards; }
    @keyframes checkmark { 0% { stroke-dashoffset: 100; } 100% { stroke-dashoffset: 0; } }

    .confetti { position: absolute; animation: confetti 2s ease forwards; }
    .ticket-card { transition: all 0.3s ease; }
    .ticket-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15); }
    .action-btn { transition: all 0.2s ease; }
    .action-btn:hover { transform: translateY(-2px); }
    .qr-code { background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); }
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<body class="bg-gray-50 min-h-screen">
    <!-- Confetti Container -->
    <div id="confettiContainer" class="fixed inset-0 pointer-events-none z-50 overflow-hidden"></div>

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">T</span>
                    </div>
                    <span class="font-bold text-lg">TICS</span>
                </a>

                <!-- Progress Steps - Completed -->
                <div class="hidden md:flex items-center gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-sm text-gray-500">Coș</span>
                    </div>
                    <div class="w-12 h-px bg-green-500"></div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-sm text-gray-500">Plată</span>
                    </div>
                    <div class="w-12 h-px bg-green-500"></div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Confirmare</span>
                    </div>
                </div>

                <a href="/biletele-mele" class="text-sm text-indigo-600 font-medium hover:underline">Biletele mele →</a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 lg:px-8 py-12">
        <!-- Success Header -->
        <div class="text-center mb-10">
            <div class="relative inline-block mb-6">
                <div class="success-circle w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mx-auto shadow-lg shadow-green-500/30">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path class="checkmark-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="absolute -top-2 -right-2 w-8 h-8 bg-amber-400 rounded-full flex items-center justify-center animate-float">
                    <span class="text-lg">🎉</span>
                </div>
            </div>

            <h1 class="text-3xl font-bold text-gray-900 mb-2 animate-fadeInUp" style="animation-delay: 0.3s">Comandă confirmată!</h1>
            <p class="text-gray-500 animate-fadeInUp" style="animation-delay: 0.4s">Biletele au fost trimise pe email la <strong class="text-gray-900" id="userEmail">email@exemplu.com</strong></p>

            <!-- Order Number -->
            <div class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-gray-100 rounded-full animate-fadeInUp" style="animation-delay: 0.5s">
                <span class="text-sm text-gray-500">Comandă:</span>
                <span class="font-mono font-bold text-gray-900" id="orderNumber">#TICS-2026-001234</span>
                <button onclick="copyOrderNumber()" class="p-1 hover:bg-gray-200 rounded transition-colors">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
            </div>
        </div>

        <!-- Points Earned Banner -->
        <div class="bg-gradient-to-r from-amber-400 to-orange-500 rounded-2xl p-5 mb-8 flex items-center gap-4 text-white animate-fadeInUp" style="animation-delay: 0.6s">
            <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center animate-float">
                <span class="text-3xl">🎁</span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-lg">Ai câștigat <span id="earnedPoints">70</span> puncte cadou!</p>
                <p class="text-white/80 text-sm">Poți folosi punctele pentru reduceri la comenzi viitoare</p>
            </div>
            <a href="/puncte" class="hidden sm:block px-4 py-2 bg-white text-amber-600 font-semibold rounded-xl hover:bg-amber-50 transition-colors">
                Vezi punctele
            </a>
        </div>

        <!-- Tickets -->
        <div class="space-y-4 mb-8" id="ticketsList">
            <!-- Tickets loaded dynamically or demo content -->
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8 animate-fadeInUp" style="animation-delay: 0.9s">
            <h3 class="font-bold text-gray-900 mb-4">Detalii plată</h3>
            <div class="grid sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-500 mb-1">Metodă de plată</p>
                    <p class="font-medium text-gray-900" id="paymentMethod">•••• •••• •••• 4242</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Total plătit</p>
                    <p class="font-bold text-gray-900 text-xl" id="totalPaid">0 RON</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Data plății</p>
                    <p class="font-medium text-gray-900" id="paymentDate"><?= date('d M Y, H:i') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Factură</p>
                    <a href="#" class="font-medium text-indigo-600 hover:underline flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarcă PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- What's Next -->
        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6 mb-8 animate-fadeInUp" style="animation-delay: 1s">
            <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="text-xl">📋</span>
                Ce urmează?
            </h3>
            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Verifică email-ul</p>
                        <p class="text-sm text-gray-500">Ai primit biletele și confirmarea comenzii pe email</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-sm">📱</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Salvează biletele</p>
                        <p class="text-sm text-gray-500">Poți accesa biletele oricând din contul tău sau din email</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-sm">🎫</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">La eveniment</p>
                        <p class="text-sm text-gray-500">Prezintă codul QR de pe bilet (pe telefon sau printat) la intrare</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share -->
        <div class="text-center animate-fadeInUp" style="animation-delay: 1.1s">
            <p class="text-gray-500 mb-4">Spune-le și prietenilor!</p>
            <div class="flex items-center justify-center gap-3">
                <button class="w-12 h-12 bg-[#1877F2] rounded-full flex items-center justify-center text-white hover:opacity-90 transition-opacity">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                </button>
                <button class="w-12 h-12 bg-black rounded-full flex items-center justify-center text-white hover:opacity-90 transition-opacity">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </button>
                <button class="w-12 h-12 bg-[#25D366] rounded-full flex items-center justify-center text-white hover:opacity-90 transition-opacity">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                </button>
            </div>
        </div>

        <!-- CTA -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-10 animate-fadeInUp" style="animation-delay: 1.2s">
            <a href="/evenimente" class="w-full sm:w-auto px-8 py-4 bg-gray-900 text-white font-bold rounded-xl hover:bg-gray-800 transition-colors text-center">
                Descoperă alte evenimente
            </a>
            <a href="/biletele-mele" class="w-full sm:w-auto px-8 py-4 bg-gray-100 text-gray-900 font-bold rounded-xl hover:bg-gray-200 transition-colors text-center">
                Vezi biletele mele
            </a>
        </div>
    </main>

    <!-- Footer Mini -->
    <footer class="bg-white border-t border-gray-200 mt-16 py-6">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-gray-900 rounded flex items-center justify-center">
                        <span class="text-white font-bold text-xs">T</span>
                    </div>
                    <span>© <?= date('Y') ?> TICS.ro • Powered by Tixello</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="/ajutor" class="hover:text-gray-900 transition-colors">Ajutor</a>
                    <a href="/contact" class="hover:text-gray-900 transition-colors">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Confetti effect
        function createConfetti() {
            const container = document.getElementById('confettiContainer');
            const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'];

            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.top = '100%';
                    confetti.style.width = Math.random() * 10 + 5 + 'px';
                    confetti.style.height = confetti.style.width;
                    confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                    confetti.style.animationDuration = Math.random() * 1 + 1.5 + 's';
                    container.appendChild(confetti);

                    setTimeout(() => confetti.remove(), 3000);
                }, i * 50);
            }
        }

        // Trigger confetti on load
        setTimeout(createConfetti, 500);

        // Copy order number
        function copyOrderNumber() {
            const orderNum = document.getElementById('orderNumber').textContent;
            navigator.clipboard.writeText(orderNum);
            alert('Număr comandă copiat!');
        }

        // Load order data (demo)
        document.addEventListener('DOMContentLoaded', function() {
            // Clear cart after successful order
            localStorage.removeItem('tics_cart');

            // Demo ticket
            const ticketsList = document.getElementById('ticketsList');
            ticketsList.innerHTML = `
                <div class="ticket-card bg-white rounded-2xl border border-gray-200 overflow-hidden animate-fadeInUp" style="animation-delay: 0.7s">
                    <div class="flex flex-col sm:flex-row">
                        <div class="sm:w-48 h-32 sm:h-auto relative">
                            <img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:bg-gradient-to-r"></div>
                            <span class="absolute bottom-2 left-2 sm:bottom-auto sm:top-2 px-2 py-1 bg-green-500 text-white text-xs font-bold rounded">2 bilete</span>
                        </div>
                        <div class="flex-1 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Eveniment Demo</h3>
                                    <p class="text-gray-500 mb-3">Locație Demo, București</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-sm text-gray-700">📅 14 Feb 2026</span>
                                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-sm text-gray-700">🕐 20:00</span>
                                        <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium">General Admission</span>
                                    </div>
                                </div>
                                <div class="qr-code w-20 h-20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-16 h-16 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100">
                                <button class="action-btn flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Adaugă în calendar
                                </button>
                                <button class="action-btn flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Descarcă PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Set demo values
            document.getElementById('totalPaid').textContent = '349 RON';
        });
    </script>
</body>
</html>
