<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = '403 - Acces Interzis';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - AmBilet.ro</title>
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
                        secondary: '#1E293B',
                        muted: '#64748B',
                        surface: '#F8FAFC',
                        border: '#E2E8F0',
                    }
                }
            }
        }
    </script>
</head>
<body class="flex flex-col min-h-screen font-['Plus_Jakarta_Sans'] text-white bg-gradient-to-br from-secondary to-slate-950">
    <!-- Header -->
    <header class="p-6">
        <a href="/" class="inline-flex items-center gap-3">
            <svg class="w-10 h-10" viewBox="0 0 48 48" fill="none">
                <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#grad403)"/>
                <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                <defs><linearGradient id="grad403" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
            </svg>
            <span class="text-2xl font-bold"><span class="text-white">Am</span><span class="text-red-400">Bilet</span></span>
        </a>
    </header>

    <!-- Main Content -->
    <div class="flex items-center justify-center flex-1 p-8">
        <div class="max-w-xl text-center">
            <!-- Illustration -->
            <svg class="w-64 h-48 mx-auto mb-8" viewBox="0 0 280 200" fill="none">
                <path d="M140 20L60 50V100C60 145 95 175 140 190C185 175 220 145 220 100V50L140 20Z" fill="#334155" stroke="#475569" stroke-width="3"/>
                <path d="M140 35L75 60V100C75 138 105 163 140 175C175 163 205 138 205 100V60L140 35Z" fill="#1E293B"/>
                <rect x="115" y="95" width="50" height="40" rx="4" fill="#F97316"/>
                <rect x="120" y="100" width="40" height="30" rx="2" fill="#FB923C"/>
                <path d="M125 95V80C125 71.716 131.716 65 140 65C148.284 65 155 71.716 155 80V95" stroke="#F97316" stroke-width="8" stroke-linecap="round"/>
                <circle cx="140" cy="112" r="6" fill="#1E293B"/>
                <path d="M140 115V125" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                <path d="M45 40L55 50M55 40L45 50" stroke="#EF4444" stroke-width="3" stroke-linecap="round"/>
                <path d="M225 60L235 70M235 60L225 70" stroke="#EF4444" stroke-width="3" stroke-linecap="round"/>
                <path d="M50 150L60 160M60 150L50 160" stroke="#EF4444" stroke-width="3" stroke-linecap="round"/>
            </svg>

            <div class="text-8xl font-extrabold bg-gradient-to-r from-orange-500 to-orange-400 bg-clip-text text-transparent mb-4">403</div>
            <h1 class="mb-3 text-2xl font-bold">Acces interzis</h1>
            <p class="mb-8 text-gray-400">Nu ai permisiunea sa accesezi aceasta pagina. Verifica daca esti autentificat cu contul corect sau contacteaza administratorul pentru acces.</p>

            <!-- Reasons -->
            <div class="p-5 mb-8 text-left border rounded-xl bg-orange-500/10 border-orange-500/30">
                <h2 class="mb-3 text-sm font-semibold text-orange-400">Posibile motive:</h2>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li class="flex items-center gap-2">
                        <svg class="flex-shrink-0 w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Nu esti autentificat sau sesiunea a expirat
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="flex-shrink-0 w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Contul tau nu are permisiunile necesare
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="flex-shrink-0 w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Link-ul pe care l-ai accesat este invalid
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4">
                <a href="/autentificare" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-all rounded-xl bg-gradient-to-r from-primary to-red-700 hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/40">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Autentifica-te
                </a>
                <a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors border rounded-xl bg-white/10 border-white/20 hover:bg-white/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Pagina principala
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="p-6 text-sm text-center text-gray-500">
        <p>Crezi ca este o eroare? <a href="/contact" class="text-gray-400 hover:text-white">Contacteaza suportul</a></p>
    </footer>
</body>
</html>
