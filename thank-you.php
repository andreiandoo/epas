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
$scriptsExtra = <<<'SCRIPTS'
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

    .tickets-scroll { display: flex; gap: 16px; overflow-x: visible; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 20px 0; scrollbar-width: none; }
    .tickets-scroll::-webkit-scrollbar { display: none; }

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

<script>
const ThankYouPage = {
    order: null,

    async init() {
        this.createConfetti();
        await this.loadOrderData();
    },

    createConfetti() {
        const container = document.getElementById('confetti');
        const colors = ['#A51C30', '#10B981', '#E67E22', '#3B82F6', '#8B5CF6', '#EC4899'];

        for (let i = 0; i < 80; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 3) + 's';

                if (Math.random() > 0.5) {
                    confetti.style.borderRadius = '0';
                    confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                }

                container.appendChild(confetti);
                setTimeout(() => confetti.remove(), 6000);
            }, i * 50);
        }
    },

    async loadOrderData() {
        const urlParams = new URLSearchParams(window.location.search);
        // Read from 'order' param (our param) or 'orderId' (Netopia adds this on redirect)
        const orderRef = urlParams.get('order') || urlParams.get('orderId');

        if (!orderRef) {
            this.showDemoData();
            return;
        }

        // Clean up duplicate URL params (keep only ?order=)
        if (urlParams.get('orderId') || urlParams.has('orderId')) {
            const cleanUrl = window.location.pathname + '?order=' + encodeURIComponent(orderRef);
            history.replaceState(null, '', cleanUrl);
        }

        try {
            const response = await AmbiletAPI.get(`/order-confirmation/${orderRef}`);
            if (response.success && response.data?.order) {
                this.order = response.data.order;
                this.renderOrderData();
            } else {
                console.warn('Order data not found in response:', response);
                this.showDemoData();
            }
        } catch (error) {
            console.error('Failed to load order:', error);
            this.showDemoData();
        }
    },

    showDemoData() {
        // Show demo/placeholder data
        document.getElementById('printingText').textContent = 'Biletele sunt gata!';
        document.getElementById('ticketsCount').textContent = 'Verifică email-ul pentru bilete';
        document.getElementById('buyerEmail').textContent = 'Email-ul tău';
    },

    renderOrderData() {
        const order = this.order;

        // Update texts
        document.getElementById('printingText').textContent = 'Biletele sunt gata!';
        document.getElementById('buyerEmail').textContent = order.customer_email || 'Email-ul tău';

        // Event info
        const eventInfo = document.getElementById('eventInfo');
        const event = order.event;
        if (event) {
            const eventTitle = event.name || event.title || 'Eveniment';
            const eventDate = event.date ? AmbiletUtils.formatDate(event.date) : '';
            const eventTime = event.doors_open || (event.date ? new Date(event.date).toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}) : '');
            // Venue may be string or translatable object {en: "...", ro: "..."}
            const venue = (typeof event.venue === 'object' && event.venue !== null) ? (event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '') : (event.venue || '');
            eventInfo.innerHTML = `
                <img src="${getStorageUrl(event.image)}" alt="${eventTitle}" class="object-cover w-20 h-20 rounded-xl" loading="lazy" onerror="this.style.display='none'">
                <div>
                    <h3 class="font-bold text-secondary">${eventTitle}</h3>
                    <p class="mt-1 text-sm text-muted">${eventDate}${eventTime ? ' • ' + eventTime : ''}</p>
                    <p class="text-sm text-muted">${venue}${event.city ? ', ' + event.city : ''}</p>
                </div>
            `;
        }

        // Tickets
        const tickets = order.tickets || [];
        this.renderTickets(Array.isArray(tickets) ? tickets : Object.values(tickets));

        // Ticket summary (items grouped by type + individual seat assignments)
        const ticketsSummary = document.getElementById('ticketsSummary');
        const seatedTickets = (order.tickets || []).filter(t => t.seat);
        if (order.items && order.items.length > 0) {
            let html = `<h4 class="mb-3 font-semibold text-secondary">Bilete achiziționate</h4>`;
            html += order.items.map(item => `
                <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                    <div>
                        <span class="font-medium text-secondary">${item.name}</span>
                        <span class="ml-1 text-sm text-muted">× ${item.quantity}</span>
                    </div>
                    <span class="font-semibold">${AmbiletUtils.formatCurrency(item.total)}</span>
                </div>
            `).join('');
            // Show insurance info if any ticket has insurance
            const insuredTickets = (order.tickets || []).filter(t => t.has_insurance);
            if (insuredTickets.length > 0) {
                html += `<div class="pt-3 mt-3 border-t border-border">
                    <div class="flex items-center gap-2 text-sm text-green-700">
                        <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span class="font-medium">${insuredTickets.length} bilet${insuredTickets.length > 1 ? 'e' : ''} asigurat${insuredTickets.length > 1 ? 'e' : ''} cu taxa de retur</span>
                    </div>
                </div>`;
            }
            // Show individual seat assignments if tickets have seats
            if (seatedTickets.length > 0) {
                html += `<div class="pt-3 mt-3 border-t border-border">
                    <p class="mb-2 text-xs font-medium tracking-wide uppercase text-muted">Locuri atribuite</p>
                    ${seatedTickets.map(t => `
                        <div class="flex items-center gap-2 py-1 text-sm">
                            <span class="text-muted">${t.type || 'Bilet'}</span>
                            <span class="font-medium text-secondary">
                                ${[t.seat.section_name, t.seat.row_label ? 'Rând ' + t.seat.row_label : '', t.seat.seat_number ? 'Loc ' + t.seat.seat_number : ''].filter(Boolean).join(', ')}
                            </span>
                        </div>
                    `).join('')}
                </div>`;
            }
            ticketsSummary.innerHTML = html;
        }

        // Payment summary
        const subtotal = parseFloat(order.subtotal) || 0;
        const total = parseFloat(order.total) || 0;
        const discount = parseFloat(order.discount) || 0;
        const serviceFee = parseFloat(order.service_fee) || 0;
        const insuranceAmount = parseFloat(order.insurance_amount) || 0;
        const currency = order.currency || 'RON';

        document.getElementById('paymentSummary').innerHTML = `
            <h4 class="mb-3 font-semibold text-secondary">Sumar plată</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted">Subtotal</span>
                    <span>${AmbiletUtils.formatCurrency(subtotal)}</span>
                </div>
                ${serviceFee > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Comision serviciu</span>
                    <span>${AmbiletUtils.formatCurrency(serviceFee)}</span>
                </div>
                ` : ''}
                ${insuranceAmount > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Taxa de retur</span>
                    <span>${AmbiletUtils.formatCurrency(insuranceAmount)}</span>
                </div>
                ` : ''}
                ${discount > 0 ? `
                <div class="flex justify-between text-success">
                    <span>Reducere</span>
                    <span>-${AmbiletUtils.formatCurrency(discount)}</span>
                </div>
                ` : ''}
                <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                    <span>Total plătit</span>
                    <span class="text-primary">${AmbiletUtils.formatCurrency(total)}</span>
                </div>
            </div>
        `;

        // Payment method
        if (order.payment_method) {
            const cardEl = document.getElementById('cardNumber');
            if (cardEl) {
                cardEl.textContent = order.payment_method;
            }
        }

        // Hide points section if no points data
        const pointsEl = document.getElementById('pointsEarned');
        if (pointsEl) {
            pointsEl.style.display = 'none';
        }

        // Wire up download button
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn && order.can_download_tickets) {
            downloadBtn.href = '#';
            downloadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.downloadTickets();
            });
        }

        // Wire up calendar button
        const calendarBtn = document.getElementById('calendarBtn');
        if (calendarBtn && order.event) {
            calendarBtn.href = '#';
            calendarBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.addToCalendar();
            });
        }

        // Wire up share buttons
        const eventUrl = window.location.origin + '/bilete/' + (order.event?.slug || 'event');
        const eventTitle = order.event?.name || order.event?.title || 'Eveniment';
        const shareFb = document.getElementById('shareFb');
        if (shareFb) {
            shareFb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(eventUrl);
            shareFb.target = '_blank';
        }
        const shareWa = document.getElementById('shareWa');
        if (shareWa) {
            shareWa.href = 'https://wa.me/?text=' + encodeURIComponent(eventTitle + ' - ' + eventUrl);
            shareWa.target = '_blank';
        }
    },

    downloadTickets() {
        const order = this.order;
        if (!order || !order.tickets) return;

        const event = order.event;
        const eventTitle = event?.name || event?.title || 'Eveniment';
        const eventDate = event?.date ? AmbiletUtils.formatDate(event.date) : '';
        const venue = typeof event?.venue === 'object' ? (event.venue?.ro || event.venue?.en || '') : (event?.venue || '');
        const siteName = window.AMBILET?.siteName || 'bilete.online';

        const ticketsHtml = order.tickets.map((ticket, idx) => {
            const seatInfo = ticket.seat ? [
                ticket.seat.section_name,
                ticket.seat.row_label ? 'Rând ' + ticket.seat.row_label : '',
                ticket.seat.seat_number ? 'Loc ' + ticket.seat.seat_number : ''
            ].filter(Boolean).join(' | ') : '';

            return `
                <div style="page-break-inside: avoid; border: 2px solid #1E293B; border-radius: 12px; padding: 24px; margin-bottom: 24px; max-width: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px dashed #E2E8F0;">
                        <div>
                            <div style="font-size: 11px; color: #64748B; text-transform: uppercase;">${siteName}</div>
                            <div style="font-size: 18px; font-weight: 700;">${ticket.type || 'Bilet'}</div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #64748B;">${idx + 1} / ${order.tickets.length}</div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; color: #64748B;">EVENIMENT</div>
                        <div style="font-size: 16px; font-weight: 600;">${eventTitle}</div>
                    </div>
                    <div style="display: flex; gap: 24px; margin-bottom: 12px;">
                        <div><div style="font-size: 11px; color: #64748B;">DATA</div><div style="font-weight: 600;">${eventDate}</div></div>
                        <div><div style="font-size: 11px; color: #64748B;">LOCAȚIE</div><div style="font-weight: 600;">${venue}${event?.city ? ', ' + event.city : ''}</div></div>
                    </div>
                    ${seatInfo ? `<div style="margin-bottom: 12px; padding: 8px 12px; background: #F1F5F9; border-radius: 8px; font-weight: 600;">${seatInfo}</div>` : ''}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div><div style="font-size: 11px; color: #64748B;">PARTICIPANT</div><div style="font-weight: 500;">${ticket.attendee_name || order.customer_name || ''}</div></div>
                        <div style="text-align: right;"><div style="font-size: 11px; color: #64748B;">PREȚ</div><div style="font-weight: 700; color: #A51C30;">${AmbiletUtils.formatCurrency(ticket.price)}</div></div>
                    </div>
                    <div style="text-align: center; padding-top: 12px; border-top: 1px solid #E2E8F0;">
                        ${(ticket.barcode || ticket.code) ? `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(ticket.barcode || ticket.code)}" style="width: 150px; height: 150px;" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" />
                        <div style="display:none;padding:10px;border:2px solid #1E293B;border-radius:8px;font-family:monospace;font-size:14px;font-weight:bold;word-break:break-all">${ticket.barcode || ticket.code}</div>` : '<div style="padding:10px;color:#94A3B8;font-size:12px;">Cod indisponibil</div>'}
                        <div style="font-family: monospace; font-size: 11px; color: #64748B; margin-top: 6px;">${ticket.barcode || ticket.code || ''}</div>
                    </div>
                </div>
            `;
        }).join('');

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`<!DOCTYPE html><html><head><title>Bilete - ${order.order_number}</title>
            <style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; color: #1E293B; }
            @media print { body { padding: 0; } }</style></head>
            <body><div style="max-width: 500px; margin: 0 auto;">${ticketsHtml}</div>
            <scr` + `ipt>
            // Wait for QR code images to load before printing
            function waitForImages() {
                var imgs = document.querySelectorAll('img');
                var loaded = 0;
                var total = imgs.length;
                if (total === 0) { window.print(); return; }
                imgs.forEach(function(img) {
                    if (img.complete) { loaded++; if (loaded >= total) window.print(); }
                    else {
                        img.onload = function() { loaded++; if (loaded >= total) window.print(); };
                        img.onerror = function() { loaded++; if (loaded >= total) window.print(); };
                    }
                });
                // Fallback: print after 3 seconds even if images haven't loaded
                setTimeout(function() { window.print(); }, 3000);
            }
            waitForImages();
            <\/scr` + `ipt></body></html>`);
        printWindow.document.close();
    },

    addToCalendar() {
        const event = this.order?.event;
        if (!event) return;

        const title = event.name || event.title || 'Eveniment';
        const venue = typeof event.venue === 'object' ? (event.venue?.ro || event.venue?.en || '') : (event.venue || '');
        const location = venue + (event.city ? ', ' + event.city : '');
        const startDate = event.date ? new Date(event.date) : null;

        if (!startDate) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.info('Data evenimentului nu este disponibilă.');
            }
            return;
        }

        // Format dates for Google Calendar (YYYYMMDDTHHmmssZ)
        const formatGCal = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        const endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000); // +3 hours default

        const gcalUrl = 'https://www.google.com/calendar/render?action=TEMPLATE'
            + '&text=' + encodeURIComponent(title)
            + '&dates=' + formatGCal(startDate) + '/' + formatGCal(endDate)
            + '&location=' + encodeURIComponent(location)
            + '&details=' + encodeURIComponent('Bilete achiziționate pe ' + (window.AMBILET?.siteName || 'bilete.online'));

        window.open(gcalUrl, '_blank');
    },

    renderTickets(tickets) {
        const container = document.getElementById('ticketsScroll');
        const indicators = document.getElementById('scrollIndicators');
        const total = tickets.length;

        if (total === 0) {
            document.getElementById('ticketsCount').textContent = 'Nu există bilete';
            return;
        }

        document.getElementById('ticketsCount').textContent = `${total} bilet${total > 1 ? 'e' : ''} pentru ${this.order?.event?.name || this.order?.event?.title || 'eveniment'}`;

        container.innerHTML = tickets.map((ticket, idx) => this.renderTicketCard(ticket, idx, total)).join('');

        // Scroll indicators
        indicators.innerHTML = tickets.map((_, idx) =>
            `<div class="scroll-dot ${idx === 0 ? 'active' : ''}" data-index="${idx}"></div>`
        ).join('');

        // Scroll event listener
        container.addEventListener('scroll', () => {
            const cardWidth = container.querySelector('.ticket-card')?.offsetWidth + 16 || 296;
            const activeIndex = Math.round(container.scrollLeft / cardWidth);

            indicators.querySelectorAll('.scroll-dot').forEach((dot, i) => {
                dot.classList.toggle('active', i === activeIndex);
            });
        });

        // Click on indicators
        indicators.querySelectorAll('.scroll-dot').forEach(dot => {
            dot.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                const cardWidth = container.querySelector('.ticket-card')?.offsetWidth + 16 || 296;
                container.scrollTo({ left: index * cardWidth, behavior: 'smooth' });
            });
        });
    },

    renderTicketCard(ticket, idx, total) {
        const barcodeLines = Array(15).fill(0).map(() =>
            `<div class="barcode-line" style="height: ${20 + Math.random() * 15}px;"></div>`
        ).join('');

        const event = this.order?.event;
        const eventTitle = event?.name || event?.title || 'Eveniment';
        const eventDate = event?.date ? AmbiletUtils.formatDate(event.date, 'medium') : '';
        const eventTime = event?.doors_open || (event?.date ? new Date(event.date).toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}) : '');
        const eventVenue = event?.venue ? (typeof event.venue === 'object' ? (event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '') : event.venue) : '';

        return `
            <div class="ticket-card" data-index="${idx}">
                <div class="ticket-card-header">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs opacity-70">${SITE_NAME} TICKET</span>
                        <span class="text-xs font-bold bg-white/20 px-2 py-0.5 rounded">${idx + 1} / ${total}</span>
                    </div>
                    <h3 class="text-lg font-bold">${ticket.type || ticket.type_name || 'Bilet'}</h3>
                </div>
                <div class="ticket-card-body">
                    <div class="ticket-dashed-line"></div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="text-xs tracking-wide uppercase text-muted">Eveniment</p>
                            <p class="font-bold text-secondary">${eventTitle}</p>
                        </div>
                        <div class="flex gap-4">
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Data</p>
                                <p class="font-semibold text-secondary">${eventDate}</p>
                            </div>
                            ${eventTime ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Ora</p>
                                <p class="font-semibold text-secondary">${eventTime}</p>
                            </div>
                            ` : ''}
                        </div>
                        <div>
                            <p class="text-xs tracking-wide uppercase text-muted">Locație</p>
                            <p class="font-semibold text-secondary">${eventVenue}${event?.city ? ', ' + event.city : ''}</p>
                        </div>
                        ${ticket.seat ? `
                        <div class="flex flex-wrap gap-4">
                            ${ticket.seat.section_name ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Secțiune</p>
                                <p class="font-semibold text-secondary">${ticket.seat.section_name}</p>
                            </div>` : ''}
                            ${ticket.seat.row_label ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Rând</p>
                                <p class="font-semibold text-secondary">${ticket.seat.row_label}</p>
                            </div>` : ''}
                            ${ticket.seat.seat_number ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Loc</p>
                                <p class="font-semibold text-secondary">${ticket.seat.seat_number}</p>
                            </div>` : ''}
                        </div>
                        ` : ''}
                        <div class="flex items-center justify-between pt-2">
                            <div>
                                <p class="text-xs text-muted">Participant</p>
                                <p class="text-sm font-semibold text-secondary">${ticket.attendee_name || this.order?.billing_address || 'Participant'}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-muted">Preț</p>
                                <p class="font-bold text-primary">${AmbiletUtils.formatCurrency(ticket.price)}</p>
                            </div>
                        </div>
                        ${ticket.has_insurance ? `
                        <div class="inline-flex items-center gap-1 px-2 py-1 mt-2 text-xs font-medium text-green-700 rounded-full bg-green-50">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Asigurat - Taxa de retur
                        </div>
                        ` : ''}
                    </div>

                    <div class="ticket-barcode">${barcodeLines}</div>
                    <p class="text-center text-[10px] text-muted mt-2">${ticket.barcode || ticket.code || ''}</p>
                </div>
            </div>
        `;
    },

    copyLink() {
        const url = window.location.origin + '/bilete/' + (this.order?.event?.slug || 'event');
        navigator.clipboard.writeText(url).then(() => {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.success('Link copiat în clipboard!');
            } else {
                alert('Link copiat în clipboard!');
            }
        });
    }
};

const SITE_NAME = '<?= SITE_NAME ?>';

document.addEventListener('DOMContentLoaded', () => ThankYouPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
