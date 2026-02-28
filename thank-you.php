<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Comandă confirmată!';
$pageDescription = 'Mulțumim pentru achiziție! Biletele tale au fost procesate cu succes.';
$orderRef = $_GET['order'] ?? '';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Confetti Container -->
    <div class="confetti-container" id="confetti"></div>

    <!-- Progress Steps - All Complete -->
    <div class="bg-white border-b border-gray-200 mt-18 mobile:mt-20">
        <div class="px-4 py-4 mx-auto max-w-7xl">
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-white bg-green-500 rounded-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span class="text-sm font-medium text-green-600">Coș</span>
                </div>
                <div class="w-12 h-px bg-green-500"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-white bg-green-500 rounded-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span class="text-sm font-medium text-green-600">Checkout</span>
                </div>
                <div class="w-12 h-px bg-green-500"></div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 text-white bg-green-500 rounded-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span class="text-sm font-semibold text-green-600">Confirmare</span>
                </div>
            </div>
            <?php if ($orderRef): ?>
            <p class="mt-2 text-sm text-center text-gray-500">Comandă #<?= htmlspecialchars($orderRef) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-4xl px-4 py-6 mx-auto">
        <!-- Success Message -->
        <div class="mb-6 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 mb-4 rounded-full bg-success/20">
                <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="mb-2 text-3xl font-extrabold md:text-4xl text-secondary">Plata a fost procesată!</h1>
            <p id="printingText" class="text-lg text-muted">Biletele tale se printează...</p>
        </div>

        <!-- Printer Section -->
        <div class="printer-section">
            <div class="printer-container">
                <div class="printer">
                    <!-- Printing tickets (animation) -->
                    <div class="printing-ticket print-ticket-1">
                        <div class="ticket">
                            <div class="ticket-header">
                                <p class="text-[10px] opacity-70"><?= SITE_NAME ?></p>
                                <p class="text-xs font-bold">Bilet</p>
                            </div>
                            <div class="ticket-body">
                                <p class="text-[10px] text-muted">Eveniment</p>
                                <p class="text-xs font-bold text-secondary">Loading...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Printer -->
                    <div class="printer-body">
                        <div class="printer-slot"></div>
                        <div class="printer-light"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Carousel (appears after printing) -->
        <div class="mt-6 tickets-carousel" id="ticketsCarousel">
            <div class="mb-4 text-center">
                <h2 class="mb-1 text-xl font-bold text-secondary">Biletele tale sunt gata!</h2>
                <p id="ticketsCount" class="text-sm text-muted">Se încarcă...</p>
            </div>

            <!-- Swipe hint for mobile -->
            <div class="swipe-hint">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
                Glisează pentru a vedea toate biletele
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </div>

            <!-- Scrollable tickets container -->
            <div class="tickets-scroll" id="ticketsScroll"></div>

            <!-- Scroll indicators -->
            <div class="scroll-indicators" id="scrollIndicators"></div>
        </div>

        <!-- Email Confirmation -->
        <div class="flex items-center gap-4 p-4 mt-8 bg-white border email-animation rounded-2xl border-border">
            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-success/10 rounded-xl">
                <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-secondary">Biletele au fost trimise pe email</p>
                <p id="buyerEmail" class="text-sm text-muted">Se încarcă...</p>
            </div>
            <svg class="flex-shrink-0 w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>

        <!-- Order Details -->
        <div class="mt-8 overflow-hidden bg-white border content-section delay-1 rounded-3xl border-border" id="orderDetails">
            <div class="p-6 border-b border-border">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-secondary">Detalii comandă</h2>
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-success/10 text-success">Confirmată</span>
                </div>
            </div>

            <div class="p-6">
                <!-- Event Info -->
                <div id="eventInfo" class="flex gap-4 mb-6">
                    <div class="w-20 h-20 skeleton rounded-xl"></div>
                    <div class="flex-1">
                        <div class="w-3/4 h-5 mb-2 rounded skeleton"></div>
                        <div class="w-1/2 h-4 mb-1 rounded skeleton"></div>
                        <div class="w-2/3 h-4 rounded skeleton"></div>
                    </div>
                </div>

                <!-- Tickets Summary -->
                <div id="ticketsSummary" class="p-4 mb-6 bg-surface rounded-xl">
                    <h4 class="mb-3 font-semibold text-secondary">Bilete achiziționate</h4>
                    <div class="h-16 rounded skeleton"></div>
                </div>

                <!-- Payment Summary -->
                <div class="pt-6 border-t border-border">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h4 class="mb-3 font-semibold text-secondary">Metoda de plată</h4>
                            <div class="flex items-center gap-3 p-3 bg-surface rounded-xl">
                                <div class="flex items-center justify-center w-12 h-8 rounded bg-gradient-to-r from-blue-600 to-blue-800">
                                    <span class="text-white text-[8px] font-bold">NETOPIA</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-secondary">Card bancar</p>
                                    <p id="cardNumber" class="text-xs text-muted">**** **** **** ****</p>
                                </div>
                            </div>
                        </div>
                        <div id="paymentSummary">
                            <h4 class="mb-3 font-semibold text-secondary">Sumar plată</h4>
                            <div class="space-y-2 text-sm">
                                <div class="h-4 rounded skeleton"></div>
                                <div class="h-4 rounded skeleton"></div>
                                <div class="h-6 rounded skeleton"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Points Earned -->
                <div id="pointsEarned" class="flex items-center justify-between p-4 mt-6 bg-accent/10 rounded-xl">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">🎁</span>
                        <div>
                            <p class="font-semibold text-secondary">Ai câștigat puncte!</p>
                            <p class="text-sm text-muted">Sold nou: <span id="newPoints">0</span> puncte</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p id="earnedPoints" class="text-2xl font-bold text-accent">+0</p>
                        <p class="text-xs text-muted">puncte</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="grid gap-4 mt-8 content-section delay-2 md:grid-cols-2">
            <a href="#" id="downloadBtn" class="flex items-center justify-center gap-3 p-4 transition-all bg-white border rounded-2xl border-border hover:border-primary hover:shadow-lg">
                <div class="flex items-center justify-center w-12 h-12 bg-primary/10 rounded-xl">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-semibold text-secondary">Printează biletele</p>
                    <p class="text-sm text-muted">Printează sau salvează ca PDF</p>
                </div>
            </a>
            <a href="#" id="calendarBtn" class="flex items-center justify-center gap-3 p-4 transition-all bg-white border rounded-2xl border-border hover:border-primary hover:shadow-lg">
                <div class="flex items-center justify-center w-12 h-12 bg-primary/10 rounded-xl">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-semibold text-secondary">Adaugă în calendar</p>
                    <p class="text-sm text-muted">Google Calendar / iCal</p>
                </div>
            </a>
        </div>

        <!-- Share Section -->
        <div class="mt-8 text-center content-section delay-3">
            <p class="mb-4 text-muted">Spune-le și prietenilor despre eveniment!</p>
            <div class="flex items-center justify-center gap-3">
                <a href="#" id="shareFb" class="w-12 h-12 bg-[#1877F2] text-white rounded-xl flex items-center justify-center hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" id="shareWa" class="w-12 h-12 bg-[#25D366] text-white rounded-xl flex items-center justify-center hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <button onclick="ThankYouPage.copyLink()" class="flex items-center justify-center w-12 h-12 transition-all border bg-surface text-secondary rounded-xl hover:bg-primary hover:text-white border-border">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
            </div>
        </div>

        <!-- Back to Home -->
        <div class="mt-12 text-center content-section delay-4">
            <a href="/" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold text-white btn-primary rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Înapoi la pagina principală
            </a>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'STYLES'
<style>
    body { background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%); min-height: 100vh; overflow-x: hidden; }

    /* Confetti */
    .confetti-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 100; overflow: hidden; }
    .confetti { position: absolute; width: 10px; height: 10px; opacity: 0; animation: confetti-fall 4s ease-out forwards; }
    @keyframes confetti-fall {
        0% { opacity: 1; transform: translateY(-100px) rotate(0deg); }
        100% { opacity: 0; transform: translateY(100vh) rotate(720deg); }
    }

    /* Printer Animation */
    .printer-section { padding-top: 40px; padding-bottom: 20px; }
    @media (max-width: 768px) { .printer-section { padding-top: 20px; min-height: auto; } }
    .printer-container { perspective: 1000px; position: relative; }
    .printer { position: relative; width: 280px; height: 120px; margin: 0 auto; }
    @media (max-width: 768px) { .printer { width: 240px; height: 100px; } }
    .printer-body { position: absolute; bottom: 0; width: 100%; height: 80px; background: linear-gradient(145deg, #374151 0%, #1F2937 100%); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    @media (max-width: 768px) { .printer-body { height: 70px; } }
    .printer-body::before { content: ''; position: absolute; top: 15px; left: 50%; transform: translateX(-50%); width: 180px; height: 8px; background: #111827; border-radius: 4px; }
    .printer-slot { position: absolute; top: -5px; left: 50%; transform: translateX(-50%); width: 200px; height: 10px; background: #111827; border-radius: 2px; }
    @media (max-width: 768px) { .printer-slot { width: 160px; } }
    .printer-light { position: absolute; bottom: 15px; right: 20px; width: 10px; height: 10px; background: #10B981; border-radius: 50%; animation: blink 1s ease-in-out infinite; box-shadow: 0 0 10px #10B981; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

    /* Printing ticket animation */
    .printing-ticket { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); width: 180px; opacity: 0; z-index: 5; }
    @media (max-width: 768px) { .printing-ticket { width: 150px; } }
    .printing-ticket .ticket { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
    .printing-ticket .ticket-header { background: linear-gradient(135deg, var(--color-primary, #A51C30) 0%, #8B1728 100%); padding: 10px 14px; color: white; }
    .printing-ticket .ticket-body { padding: 14px; background: white; }

    @keyframes printAndExit {
        0% { opacity: 0; transform: translateX(-50%) translateY(0px); clip-path: inset(100% 0 0 0); }
        15% { opacity: 1; clip-path: inset(70% 0 0 0); }
        30% { clip-path: inset(40% 0 0 0); }
        50% { clip-path: inset(0% 0 0 0); transform: translateX(-50%) translateY(0px); }
        70% { transform: translateX(-50%) translateY(-60px); opacity: 1; }
        100% { transform: translateX(-50%) translateY(-100px); opacity: 0; clip-path: inset(0% 0 0 0); }
    }
    .print-ticket-1 { animation: printAndExit 1.5s ease-out forwards; animation-delay: 0.5s; }

    /* Tickets Carousel */
    .tickets-carousel { opacity: 0; transform: translateY(30px); animation: showCarousel 0.8s ease forwards; animation-delay: 2.5s; }
    @keyframes showCarousel { to { opacity: 1; transform: translateY(0); } }

    .tickets-scroll { display: flex; gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 20px 0; scrollbar-width: none; cursor: grab; }
    .tickets-scroll::-webkit-scrollbar { display: none; }
    .tickets-scroll.dragging { cursor: grabbing; scroll-snap-type: none; user-select: none; }

    .ticket-card { flex-shrink: 0; scroll-snap-align: center; width: 280px; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.12); transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .ticket-card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(165, 28, 48, 0.2); }
    @media (max-width: 768px) { .ticket-card { width: 260px; } }
    .ticket-card-header { background: linear-gradient(135deg, var(--color-primary, #A51C30) 0%, #8B1728 100%); padding: 16px 20px; color: white; position: relative; }
    .ticket-card-body { padding: 20px; position: relative; }
    .ticket-card-body::before, .ticket-card-body::after { content: ''; position: absolute; top: 0; width: 24px; height: 24px; background: #F8FAFC; border-radius: 50%; transform: translateY(-50%); }
    .ticket-card-body::before { left: -12px; }
    .ticket-card-body::after { right: -12px; }
    .ticket-dashed-line { position: absolute; left: 20px; right: 20px; top: 0; border-top: 2px dashed #E2E8F0; }

    .ticket-barcode { display: flex; justify-content: center; gap: 2px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #E2E8F0; }
    .barcode-line { width: 2px; background: #1E293B; border-radius: 1px; }

    .scroll-indicators { display: flex; justify-content: center; gap: 8px; margin-top: 16px; }
    .scroll-dot { width: 8px; height: 8px; border-radius: 50%; background: #E2E8F0; transition: all 0.3s ease; cursor: pointer; }
    .scroll-dot.active { width: 24px; border-radius: 4px; background: var(--color-primary, #A51C30); }

    .swipe-hint { display: none; align-items: center; justify-content: center; gap: 8px; color: #64748B; font-size: 14px; margin-bottom: 12px; animation: swipeHint 2s ease-in-out infinite; }
    @media (max-width: 768px) { .swipe-hint { display: flex; } }
    @keyframes swipeHint { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(10px); } }

    /* Content animations */
    .email-animation { opacity: 0; animation: fadeInUp 0.6s ease forwards; animation-delay: 3s; }
    .content-section { opacity: 0; animation: fadeInUp 0.6s ease forwards; }
    .delay-1 { animation-delay: 3.2s; }
    .delay-2 { animation-delay: 3.4s; }
    .delay-3 { animation-delay: 3.6s; }
    .delay-4 { animation-delay: 3.8s; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
STYLES;
$scriptsExtra .= '<script defer src="' . asset('assets/js/pages/thank-you.js') . '"></script>';

require_once __DIR__ . '/includes/scripts.php';
?>
