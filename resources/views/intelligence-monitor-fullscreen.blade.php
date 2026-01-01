<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>T.I.X.E.L.L.O Intelligence Monitor</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Tailwind CSS via CDN for standalone page --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        mono: ['JetBrains Mono', 'monospace'],
                        display: ['Orbitron', 'sans-serif'],
                    },
                },
            },
        }
    </script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @livewireStyles

    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Animations */
        @keyframes scan {
            0% { top: -10%; }
            100% { top: 110%; }
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(6, 182, 212, 0.4), 0 0 40px rgba(6, 182, 212, 0.2); }
            50% { box-shadow: 0 0 40px rgba(6, 182, 212, 0.8), 0 0 80px rgba(6, 182, 212, 0.4); }
        }

        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes voice-bar {
            0%, 100% { transform: scaleY(0.3); }
            50% { transform: scaleY(1); }
        }

        @keyframes rotate-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes data-flow {
            0% { stroke-dashoffset: 1000; }
            100% { stroke-dashoffset: 0; }
        }

        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.3; }
        }

        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }

        .animate-scan {
            animation: scan 4s linear infinite;
        }

        .animate-pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        .animate-fade-in-up {
            animation: fade-in-up 0.3s ease-out forwards;
        }

        .animate-shimmer {
            animation: shimmer 2s infinite;
        }

        .animate-voice-bar {
            animation: voice-bar 0.5s ease-in-out infinite;
        }

        .animate-rotate-slow {
            animation: rotate-slow 20s linear infinite;
        }

        .animate-blink {
            animation: blink 2s infinite;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(6, 182, 212, 0.3);
            border-radius: 2px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(6, 182, 212, 0.5);
        }

        /* Glow Effects */
        .glow-cyan {
            text-shadow: 0 0 10px rgba(6, 182, 212, 0.8), 0 0 20px rgba(6, 182, 212, 0.5), 0 0 30px rgba(6, 182, 212, 0.3);
        }

        .glow-emerald {
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.8), 0 0 20px rgba(16, 185, 129, 0.5);
        }

        .glow-amber {
            text-shadow: 0 0 10px rgba(245, 158, 11, 0.8), 0 0 20px rgba(245, 158, 11, 0.5);
        }

        /* Glass Effect */
        .glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        /* Hexagon Pattern */
        .hex-pattern {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='49' viewBox='0 0 28 49'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%2306b6d4' fill-opacity='0.05'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* Arc Reactor Style Circle */
        .arc-reactor {
            background: conic-gradient(from 0deg, transparent, rgba(6, 182, 212, 0.5), transparent, rgba(6, 182, 212, 0.3), transparent);
        }
    </style>
</head>
<body class="bg-slate-950 text-white overflow-x-hidden">
    {{-- Background Layers --}}
    <div class="fixed inset-0 pointer-events-none">
        {{-- Hex Pattern --}}
        <div class="absolute inset-0 hex-pattern opacity-30"></div>

        {{-- Gradient Overlay --}}
        <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950"></div>

        {{-- Grid Lines --}}
        <div class="absolute inset-0 opacity-10" style="background-image: linear-gradient(rgba(6, 182, 212, 0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.3) 1px, transparent 1px); background-size: 50px 50px;"></div>

        {{-- Scan Line --}}
        <div class="absolute w-full h-px bg-gradient-to-r from-transparent via-cyan-400 to-transparent opacity-50 animate-scan"></div>

        {{-- Corner Decorations --}}
        <svg class="absolute top-0 left-0 w-32 h-32 text-cyan-500 opacity-20" viewBox="0 0 100 100">
            <path d="M0 30 L30 30 L30 0" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="30" cy="30" r="3" fill="currentColor"/>
        </svg>
        <svg class="absolute top-0 right-0 w-32 h-32 text-cyan-500 opacity-20" viewBox="0 0 100 100">
            <path d="M100 30 L70 30 L70 0" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="70" cy="30" r="3" fill="currentColor"/>
        </svg>
        <svg class="absolute bottom-0 left-0 w-32 h-32 text-cyan-500 opacity-20" viewBox="0 0 100 100">
            <path d="M0 70 L30 70 L30 100" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="30" cy="70" r="3" fill="currentColor"/>
        </svg>
        <svg class="absolute bottom-0 right-0 w-32 h-32 text-cyan-500 opacity-20" viewBox="0 0 100 100">
            <path d="M100 70 L70 70 L70 100" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="70" cy="70" r="3" fill="currentColor"/>
        </svg>
    </div>

    {{-- Rotating Arc Reactor Background Element --}}
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none opacity-10">
        <div class="w-[800px] h-[800px] rounded-full arc-reactor animate-rotate-slow"></div>
    </div>

    {{-- Main Content --}}
    @livewire('intelligence-monitor')

    {{-- Fullscreen Toggle Button --}}
    <button
        onclick="toggleFullscreen()"
        class="fixed top-4 right-4 z-50 p-2 rounded-lg glass hover:bg-cyan-500/20 transition-colors"
        title="Toggle Fullscreen"
    >
        <svg id="fullscreen-icon" class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
        </svg>
    </button>

    {{-- Back to Admin Link --}}
    <a
        href="{{ route('filament.admin.pages.intelligence-monitor') }}"
        class="fixed top-4 left-4 z-50 flex items-center gap-2 px-3 py-2 rounded-lg glass hover:bg-cyan-500/20 transition-colors text-cyan-400 text-sm"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span class="font-mono">ADMIN PANEL</span>
    </a>

    <script>
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        document.addEventListener('fullscreenchange', () => {
            const icon = document.getElementById('fullscreen-icon');
            if (document.fullscreenElement) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />';
            } else {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />';
            }
        });
    </script>

    @livewireScripts
</body>
</html>
