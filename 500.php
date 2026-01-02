<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = '500 - Eroare Server';
$errorId = 'ERR-500-' . strtoupper(substr(md5(time()), 0, 6)) . '-' . time();
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
                <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#grad500)"/>
                <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                <defs><linearGradient id="grad500" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
            </svg>
            <span class="text-2xl font-bold"><span class="text-white">Am</span><span class="text-red-400">Bilet</span></span>
        </a>
    </header>

    <!-- Main Content -->
    <div class="flex items-center justify-center flex-1 p-8">
        <div class="max-w-xl text-center">
            <!-- Illustration -->
            <svg class="w-72 h-48 mx-auto mb-8" viewBox="0 0 300 200" fill="none">
                <rect x="75" y="40" width="150" height="120" rx="8" fill="#334155"/>
                <rect x="85" y="50" width="130" height="30" rx="4" fill="#1E293B"/>
                <circle cx="100" cy="65" r="6" fill="#EF4444"/>
                <circle cx="120" cy="65" r="6" fill="#F59E0B"/>
                <circle cx="140" cy="65" r="6" fill="#64748B"/>
                <rect x="160" y="58" width="45" height="4" rx="2" fill="#475569"/>
                <rect x="160" y="66" width="30" height="4" rx="2" fill="#475569"/>
                <rect x="85" y="90" width="130" height="30" rx="4" fill="#1E293B"/>
                <circle cx="100" cy="105" r="6" fill="#EF4444"/>
                <circle cx="120" cy="105" r="6" fill="#64748B"/>
                <circle cx="140" cy="105" r="6" fill="#64748B"/>
                <rect x="160" y="98" width="45" height="4" rx="2" fill="#475569"/>
                <rect x="160" y="106" width="30" height="4" rx="2" fill="#475569"/>
                <rect x="85" y="130" width="130" height="20" rx="4" fill="#1E293B"/>
                <rect x="95" y="137" width="60" height="6" rx="2" fill="#EF4444" opacity="0.5"/>
                <path d="M240 60L220 100H240L215 150L250 95H225L240 60Z" fill="#EF4444"/>
                <circle cx="80" cy="30" r="10" fill="#475569" opacity="0.5"/>
                <circle cx="95" cy="20" r="8" fill="#475569" opacity="0.3"/>
                <circle cx="70" cy="15" r="6" fill="#475569" opacity="0.2"/>
            </svg>

            <div class="text-8xl font-extrabold bg-gradient-to-r from-red-500 to-red-400 bg-clip-text text-transparent mb-4">500</div>
            <h1 class="mb-3 text-2xl font-bold">Oops! Ceva nu a functionat</h1>
            <p class="mb-8 text-gray-400">Serverul nostru a intampinat o problema neasteptata si nu a putut procesa cererea ta. Echipa noastra tehnica a fost notificata automat si lucreaza la rezolvare.</p>

            <!-- Actions -->
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-all rounded-xl bg-gradient-to-r from-primary to-red-700 hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/40">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Pagina principala
                </a>
                <button onclick="location.reload()" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors border rounded-xl bg-white/10 border-white/20 hover:bg-white/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Incearca din nou
                </button>
            </div>

            <!-- Status -->
            <div class="inline-flex items-center gap-3 px-5 py-3 border rounded-xl bg-red-500/10 border-red-500/30">
                <div class="w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-red-300">Investigam problema. Verifica status la status.ambilet.ro</span>
            </div>

            <!-- Error ID -->
            <div class="pt-8 mt-8 border-t border-white/10">
                <p class="font-mono text-xs text-gray-500">Error ID: <?= $errorId ?></p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="p-6 text-sm text-center text-gray-500">
        <p>Ai nevoie de ajutor? <a href="/contact" class="text-gray-400 hover:text-white">Contacteaza suportul</a> sau verifica <a href="/status" class="text-gray-400 hover:text-white">statusul serviciilor</a></p>
    </footer>
</body>
</html>
