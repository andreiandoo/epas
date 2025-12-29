<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = '404 - Pagina negasita';
$transparentHeader = true;
$darkPage = true;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#A51C30',
                        'primary-dark': '#8B1728',
                        secondary: '#1E293B',
                        accent: '#f87171',
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
        }
        .animate-float { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="font-sans bg-gradient-to-br from-slate-800 to-slate-900 text-slate-800 min-h-screen flex flex-col">

    <!-- Header -->
    <header class="h-[72px] bg-white/[0.03] backdrop-blur-md border-b border-white/5 flex items-center px-6 md:px-12">
        <a href="/" class="flex items-center gap-3 no-underline">
            <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                <defs>
                    <linearGradient id="logoGrad" x1="6" y1="10" x2="42" y2="38">
                        <stop stop-color="#A51C30"/>
                        <stop offset="1" stop-color="#C41E3A"/>
                    </linearGradient>
                </defs>
                <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#logoGrad)"/>
                <line x1="17" y1="15" x2="31" y2="15" stroke="white" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="15" y1="19" x2="33" y2="19" stroke="white" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
            </svg>
            <div class="text-[22px] font-extrabold flex">
                <span class="text-white">Am</span>
                <span class="text-accent">Bilet</span>
            </div>
        </a>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-6 md:p-12 relative overflow-hidden">
        <!-- Background decorations -->
        <div class="absolute -top-[300px] -right-[300px] w-[800px] h-[800px] bg-[radial-gradient(circle,rgba(165,28,48,0.1)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.08)_0%,transparent_70%)] pointer-events-none"></div>

        <div class="text-center relative z-10 max-w-xl">
            <!-- Floating Ticket Visual -->
            <div class="mb-10 relative">
                <div class="inline-block animate-float">
                    <svg class="w-[150px] h-[150px] md:w-[200px] md:h-[200px]" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="ticketGrad" x1="20" y1="40" x2="180" y2="160" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#A51C30"/>
                                <stop offset="1" stop-color="#C41E3A"/>
                            </linearGradient>
                        </defs>
                        <!-- Torn ticket shadow -->
                        <path d="M30 50C30 44.48 34.48 40 40 40H160C165.52 40 170 44.48 170 50V85L165 88L170 91V95C165 95 161 99 161 104C161 109 165 113 170 113V117L165 120L170 123V128C165 128 161 132 161 137C161 142 165 146 170 146V150C165 150 161 154 161 159C161 164 165 168 170 168V173L165 176L170 179V184L100 195L30 184V179L35 176L30 173V168C35 168 39 164 39 159C39 154 35 150 30 150V146C35 146 39 142 39 137C39 132 35 128 30 128V123L35 120L30 117V113C35 113 39 109 39 104C39 99 35 95 30 95V91L35 88L30 85V50Z" fill="url(#ticketGrad)" opacity="0.3"/>
                        <!-- Main ticket body -->
                        <path d="M30 50C30 44.48 34.48 40 40 40H160C165.52 40 170 44.48 170 50V85C164.48 85 160 89.48 160 95C160 100.52 164.48 105 170 105V150C170 155.52 165.52 160 160 160H40C34.48 160 30 155.52 30 150V105C35.52 105 40 100.52 40 95C40 89.48 35.52 85 30 85V50Z" fill="url(#ticketGrad)"/>
                        <!-- Ticket details -->
                        <line x1="60" y1="65" x2="140" y2="65" stroke="white" stroke-opacity="0.3" stroke-width="3" stroke-linecap="round"/>
                        <line x1="50" y1="80" x2="150" y2="80" stroke="white" stroke-opacity="0.4" stroke-width="3" stroke-linecap="round"/>
                        <!-- QR code area -->
                        <rect x="80" y="110" width="40" height="35" rx="4" fill="white"/>
                        <!-- Question mark -->
                        <text x="100" y="138" text-anchor="middle" font-family="Plus Jakarta Sans, sans-serif" font-size="24" font-weight="800" fill="#A51C30">?</text>
                        <!-- Torn effect lines -->
                        <path d="M25 95L35 92L30 95L35 98L25 95Z" fill="rgba(255,255,255,0.2)"/>
                        <path d="M175 95L165 92L170 95L165 98L175 95Z" fill="rgba(255,255,255,0.2)"/>
                    </svg>
                </div>
            </div>

            <!-- Error Code -->
            <div class="text-[100px] md:text-[140px] font-extrabold text-transparent bg-clip-text bg-gradient-to-br from-primary to-accent leading-none mb-4 tracking-tighter">404</div>

            <!-- Title & Message -->
            <h1 class="text-2xl md:text-[32px] font-bold text-white mb-4">Biletul nu a fost gasit</h1>
            <p class="text-base md:text-lg text-white/60 leading-relaxed mb-10">Se pare ca aceasta pagina nu exista sau a fost mutata. Poate biletul a fost deja scanat?</p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-[15px] font-bold bg-gradient-to-br from-primary to-red-600 text-white hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.4)] transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Acasa
                </a>
                <a href="/evenimente" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-[15px] font-bold bg-white/10 border border-white/20 text-white hover:bg-white/15 transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Vezi evenimente
                </a>
            </div>

            <!-- Suggestions -->
            <div class="mt-14 pt-10 border-t border-white/10">
                <div class="text-sm font-semibold text-white/40 uppercase tracking-wider mb-5">Link-uri utile</div>
                <div class="flex flex-wrap gap-6 justify-center">
                    <a href="/ajutor" class="flex items-center gap-1.5 text-[15px] text-white/60 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Centru de ajutor
                    </a>
                    <a href="/contact" class="flex items-center gap-1.5 text-[15px] text-white/60 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Contact
                    </a>
                    <a href="/cont/bilete" class="flex items-center gap-1.5 text-[15px] text-white/60 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/></svg>
                        Biletele mele
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 px-12 text-center border-t border-white/5">
        <p class="text-sm text-white/30">&copy; <?= date('Y') ?> <?= SITE_NAME ?> - Toate drepturile rezervate</p>
    </footer>
</body>
</html>
