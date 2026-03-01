<?php
/**
 * Organizer Dashboard Footer
 */
?>

<!-- Organizer Footer -->
<footer class="bg-gradient-to-br from-slate-900 to-slate-800 px-4 py-3.5 md:px-6 rounded-b-xl border-t border-primary/30 mobile:rounded-none">
    <div class="flex flex-col items-center justify-between max-w-6xl gap-3 mx-auto md:flex-row md:gap-5">
        <!-- Left: Brand & Links -->
        <div class="flex flex-col items-center gap-3 md:flex-row md:gap-5">
            <div class="flex items-center gap-2.5">
                <a href="/" class="flex items-center gap-1.5 no-underline">
                    <div class="flex items-center justify-center w-6 h-6 text-xs font-extrabold text-white rounded bg-gradient-to-br from-primary to-red-600">A</div>
                    <div class="text-sm font-bold text-white">Ambilet</div>
                </a>
                <span class="px-2.5 py-1 bg-gradient-to-br from-primary to-red-600 rounded text-[10px] font-bold text-white uppercase tracking-wide">Organizator</span>
            </div>
            <div class="hidden w-px h-5 bg-white/10 md:block"></div>
            <div class="flex items-center gap-4">
                <a href="/organizator/docs" class="flex items-center gap-1.5 text-xs text-white/90 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Documentatie
                </a>
                <a href="/organizator/api" class="flex items-center gap-1.5 text-xs text-white/90 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    API
                </a>
                <a href="/organizator/terms" class="flex items-center gap-1.5 text-xs text-white/90 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                    Termeni
                </a>
            </div>
        </div>

        <!-- Center: Status (on mobile shows first) -->
        <div class="flex items-center order-first gap-2 md:order-none">
            <div class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500/15 rounded-md text-[11px] font-semibold text-emerald-500">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                Toate sistemele functionale
            </div>
        </div>

        <!-- Right: Powered by & Help -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5 text-[10px] text-white/30">
                Powered by
                <a href="https://tixello.com" target="_blank" class="flex items-center gap-1 font-semibold transition-colors text-white/40 hover:text-white">
                    <img src="/assets/images/tixello-logo.svg" width="40" height="12" alt="Tixello" class="h-3 transition-opacity duration-200 ease-in-out opacity-50 hover:opacity-100"/>
                </a>
            </div>
            <a href="/organizator/support" class="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 border border-white/10 rounded-md text-[11px] font-semibold text-white/90 hover:bg-white/10 hover:text-white transition-all">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Suport prioritar
            </a>
        </div>
    </div>
</footer>
