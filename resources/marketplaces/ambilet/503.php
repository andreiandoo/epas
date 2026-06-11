<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = '503 - Mentenanta';
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
                <rect x="4" y="12" width="40" height="24" rx="4" fill="url(#grad503)"/>
                <path d="M4 16C4 13.7909 5.79086 12 8 12H14V36H8C5.79086 36 4 34.2091 4 32V16Z" fill="#1E293B"/>
                <circle cx="14" cy="12" r="3" fill="#F8FAFC"/><circle cx="14" cy="36" r="3" fill="#F8FAFC"/>
                <defs><linearGradient id="grad503" x1="4" y1="12" x2="44" y2="36"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
            </svg>
            <span class="text-2xl font-bold"><span class="text-white">Am</span><span class="text-red-400">Bilet</span></span>
        </a>
    </header>

    <!-- Main Content -->
    <div class="flex items-center justify-center flex-1 p-8">
        <div class="max-w-xl text-center">
            <!-- Illustration -->
            <svg class="w-64 h-48 mx-auto mb-8" viewBox="0 0 280 200" fill="none">
                <circle cx="140" cy="100" r="60" fill="#334155"/>
                <circle cx="140" cy="100" r="45" fill="#1E293B"/>
                <circle cx="140" cy="100" r="15" fill="#475569"/>
                <rect x="133" y="30" width="14" height="20" rx="2" fill="#334155"/>
                <rect x="133" y="150" width="14" height="20" rx="2" fill="#334155"/>
                <rect x="70" y="93" width="20" height="14" rx="2" fill="#334155"/>
                <rect x="190" y="93" width="20" height="14" rx="2" fill="#334155"/>
                <path d="M200 40L240 80L235 85L195 45L200 40Z" fill="#F59E0B"/>
                <circle cx="245" cy="85" r="15" fill="#F59E0B"/>
                <circle cx="245" cy="85" r="8" fill="#1E293B"/>
                <circle cx="60" cy="180" r="6" fill="#F59E0B"/>
                <circle cx="80" cy="180" r="6" fill="#F59E0B" opacity="0.6"/>
                <circle cx="100" cy="180" r="6" fill="#F59E0B" opacity="0.3"/>
            </svg>

            <div class="text-7xl font-extrabold bg-gradient-to-r from-amber-500 to-yellow-400 bg-clip-text text-transparent mb-4">503</div>
            <h1 class="mb-3 text-2xl font-bold">Suntem in mentenanta</h1>
            <p class="mb-8 text-gray-400">Lucram la imbunatatiri importante pentru a-ti oferi o experienta si mai buna. Vom reveni in curand!</p>

            <!-- Countdown -->
            <div class="p-6 mb-8 border rounded-xl bg-amber-500/10 border-amber-500/30">
                <div class="mb-3 text-xs font-semibold tracking-widest uppercase text-amber-400">Timp estimat pana la revenire</div>
                <div class="flex justify-center gap-6 mb-4">
                    <div class="text-center">
                        <div class="text-4xl font-extrabold" id="hours">01</div>
                        <div class="text-xs tracking-wider uppercase text-gray-400">Ore</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl font-extrabold" id="minutes">24</div>
                        <div class="text-xs tracking-wider uppercase text-gray-400">Minute</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl font-extrabold" id="seconds">36</div>
                        <div class="text-xs tracking-wider uppercase text-gray-400">Secunde</div>
                    </div>
                </div>
                <div class="h-1.5 bg-white/10 rounded-full overflow-hidden mb-2">
                    <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full animate-pulse" style="width: 65%"></div>
                </div>
                <p class="text-sm text-gray-500">Progres: 65% completat</p>
            </div>

            <!-- Notify Form -->
            <div class="p-5 border rounded-xl bg-white/5 border-white/10">
                <h2 class="mb-3 font-semibold">Anunta-ma cand revine</h2>
                <form class="flex flex-col gap-3 sm:flex-row" id="notifyForm">
                    <input type="email" class="flex-1 px-4 py-3 text-white border rounded-lg bg-white/10 border-white/20 placeholder-gray-500 focus:outline-none focus:border-amber-500" placeholder="Adresa ta de email">
                    <button type="submit" class="px-5 py-3 font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-amber-500 to-amber-600 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-500/30">
                        Notifica-ma
                    </button>
                </form>
            </div>

            <!-- Social Links -->
            <div class="flex justify-center gap-4 mt-8">
                <a href="#" class="flex items-center justify-center w-11 h-11 text-gray-400 transition-colors rounded-full bg-white/10 hover:bg-white/20 hover:text-white">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                </a>
                <a href="#" class="flex items-center justify-center w-11 h-11 text-gray-400 transition-colors rounded-full bg-white/10 hover:bg-white/20 hover:text-white">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="#" class="flex items-center justify-center w-11 h-11 text-gray-400 transition-colors rounded-full bg-white/10 hover:bg-white/20 hover:text-white">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="p-6 text-sm text-center text-gray-500">
        <p>Pentru urgente: <a href="mailto:urgente@ambilet.ro" class="text-gray-400 hover:text-white">urgente@ambilet.ro</a> | <a href="tel:+40212345678" class="text-gray-400 hover:text-white">+40 21 234 5678</a></p>
    </footer>

    <script>
        function updateCountdown() {
            const hours = document.getElementById('hours');
            const minutes = document.getElementById('minutes');
            const seconds = document.getElementById('seconds');

            let s = parseInt(seconds.textContent);
            let m = parseInt(minutes.textContent);
            let h = parseInt(hours.textContent);

            s--;
            if (s < 0) { s = 59; m--; }
            if (m < 0) { m = 59; h--; }
            if (h < 0) { h = 0; m = 0; s = 0; }

            hours.textContent = h.toString().padStart(2, '0');
            minutes.textContent = m.toString().padStart(2, '0');
            seconds.textContent = s.toString().padStart(2, '0');
        }
        setInterval(updateCountdown, 1000);

        document.getElementById('notifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Te vom notifica cand suntem inapoi online!');
        });
    </script>
</body>
</html>
