<?php
/**
 * bilete.online — Organizer dashboard footer (v3 design).
 *
 * Optional, rendered at the bottom of the main column (inside the layout shell
 * opened by organizer-sidebar.php). Pages that want it include it before
 * closing the main-column </div> and calling scripts.php.
 */
?>

<footer class="mt-auto border-t border-ink/10 bg-ink px-4 py-3.5 text-paper md:px-8">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 md:flex-row md:gap-5">
        <!-- Brand + links -->
        <div class="flex flex-col items-center gap-3 md:flex-row md:gap-5">
            <a href="/" class="flex items-center gap-2">
                <span class="grid h-6 w-6 place-items-center rounded bg-vermilion text-[11px] font-extrabold text-paper">b</span>
                <span class="text-sm font-bold">bilete<span class="text-vermilion">.</span>online</span>
                <span class="rounded bg-vermilion px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide text-paper">Organizator</span>
            </a>
            <div class="hidden h-5 w-px bg-paper/10 md:block"></div>
            <div class="flex items-center gap-4">
                <a href="/organizator/help" class="flex items-center gap-1.5 text-xs text-paper/70 transition hover:text-paper">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Documentație
                </a>
                <a href="/organizator/apidoc" class="flex items-center gap-1.5 text-xs text-paper/70 transition hover:text-paper">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    API
                </a>
                <a href="/termeni" class="flex items-center gap-1.5 text-xs text-paper/70 transition hover:text-paper">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                    Termeni
                </a>
            </div>
        </div>

        <!-- Status -->
        <div class="order-first flex items-center gap-2 md:order-none">
            <span class="inline-flex items-center gap-1.5 rounded-md bg-forest/20 px-3 py-1.5 text-[11px] font-bold text-mint">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-mint"></span>
                Toate sistemele funcționale
            </span>
        </div>

        <!-- Powered by + support -->
        <div class="flex items-center gap-3">
            <span class="text-[10px] text-paper/35">Operat de <a href="https://tixello.ro" target="_blank" rel="noopener" class="font-semibold text-ochre transition hover:text-paper">Tixello</a></span>
            <a href="/organizator/suport" class="inline-flex items-center gap-1.5 rounded-md border border-paper/10 bg-paper/5 px-3 py-1.5 text-[11px] font-bold text-paper/90 transition hover:bg-paper/10 hover:text-paper">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Suport prioritar
            </a>
        </div>
    </div>
</footer>
