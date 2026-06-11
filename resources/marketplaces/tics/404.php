<?php
/**
 * TICS.ro - 404 Error Page
 * Displayed when requested page is not found
 */

require_once __DIR__ . '/includes/config.php';

// Page SEO
$pageTitle = 'Pagină negăsită';
$pageDescription = 'Pagina pe care o cauți nu există sau a fost mutată. Descoperă evenimentele disponibile pe TICS.ro';
$noIndex = true;
$hideCategoriesBar = true;

// Custom styles for 404 page
$headExtra = <<<'HTML'
<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(-3deg); }
        50% { transform: translateY(-20px) rotate(3deg); }
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .animate-fadeInUp { animation: fadeInUp 0.6s ease forwards; }
    .animate-float { animation: float 4s ease-in-out infinite; }
    .animate-bounce { animation: bounce 2s ease-in-out infinite; }
    .animate-pulse-custom { animation: pulse 2s ease-in-out infinite; }

    .ticket-404 {
        position: relative;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #d946ef 100%);
    }
    .ticket-404::before,
    .ticket-404::after {
        content: '';
        position: absolute;
        width: 30px;
        height: 30px;
        background: #f9fafb;
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
    }
    .ticket-404::before { left: -15px; }
    .ticket-404::after { right: -15px; }

    .link-card {
        transition: all 0.3s ease;
    }
    .link-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
    }

    .search-input:focus {
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
</style>
HTML;

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header (minimal version)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1 flex items-center justify-center px-4 py-16">
    <div class="text-center max-w-2xl mx-auto">
        <!-- Animated Ticket -->
        <div class="mb-8 animate-fadeInUp">
            <div class="ticket-404 inline-block px-12 py-8 rounded-2xl text-white animate-float">
                <div class="text-8xl font-bold mb-2">404</div>
                <div class="flex items-center justify-center gap-2">
                    <div class="w-2 h-2 bg-white/50 rounded-full animate-pulse-custom"></div>
                    <div class="w-2 h-2 bg-white/50 rounded-full animate-pulse-custom" style="animation-delay: 0.2s"></div>
                    <div class="w-2 h-2 bg-white/50 rounded-full animate-pulse-custom" style="animation-delay: 0.4s"></div>
                </div>
            </div>
        </div>

        <!-- Message -->
        <div class="animate-fadeInUp" style="animation-delay: 0.1s">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Oops! Biletul s-a pierdut</h1>
            <p class="text-gray-500 text-lg mb-8">
                Pagina pe care o cauți nu există sau a fost mutată. <br>
                Dar nu-ți face griji, avem multe evenimente care te așteaptă!
            </p>
        </div>

        <!-- Search -->
        <div class="mb-10 animate-fadeInUp" style="animation-delay: 0.2s">
            <form action="/evenimente" method="GET" class="flex gap-2 max-w-md mx-auto">
                <div class="relative flex-1">
                    <input type="text" name="q" placeholder="Caută evenimente, artiști..."
                        class="search-input w-full pl-12 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 outline-none focus:border-indigo-500">
                    <svg class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <button type="submit" class="px-6 py-3.5 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                    Caută
                </button>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="grid sm:grid-cols-3 gap-4 mb-10 animate-fadeInUp" style="animation-delay: 0.3s">
            <a href="/" class="link-card bg-white p-5 rounded-xl border border-gray-200 text-center">
                <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Pagina principală</h3>
                <p class="text-sm text-gray-500">Descoperă evenimentele</p>
            </a>

            <a href="/evenimente" class="link-card bg-white p-5 rounded-xl border border-gray-200 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Toate evenimentele</h3>
                <p class="text-sm text-gray-500">Explorează catalogul</p>
            </a>

            <a href="/conectare" class="link-card bg-white p-5 rounded-xl border border-gray-200 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Biletele mele</h3>
                <p class="text-sm text-gray-500">Accesează contul</p>
            </a>
        </div>

        <!-- Popular Events -->
        <div class="animate-fadeInUp" style="animation-delay: 0.4s">
            <p class="text-sm text-gray-500 mb-4">Sau vezi evenimentele populare:</p>
            <div class="flex flex-wrap items-center justify-center gap-2" id="popular-events">
                <!-- Will be populated by JavaScript -->
                <a href="/evenimente/concerte" class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    Concerte
                </a>
                <a href="/evenimente/festivaluri" class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    Festivaluri
                </a>
                <a href="/evenimente/stand-up" class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    Stand-up
                </a>
                <a href="/evenimente/teatru" class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    Teatru
                </a>
                <a href="/evenimente/sport" class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                    Sport
                </a>
            </div>
        </div>

        <!-- Go Back -->
        <div class="mt-10 animate-fadeInUp" style="animation-delay: 0.5s">
            <button onclick="history.back()" class="inline-flex items-center gap-2 text-indigo-600 font-medium hover:text-indigo-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Înapoi la pagina anterioară
            </button>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
